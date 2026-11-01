package com.a2techno.bulksms

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.provider.Settings
import android.telephony.SubscriptionInfo
import android.telephony.SubscriptionManager
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.CheckBox
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.a2techno.bulksms.databinding.ActivityMainBinding

class MainActivity : AppCompatActivity() {
    private lateinit var binding: ActivityMainBinding
    private lateinit var prefs: GatewayPrefs
    private val handler = Handler(Looper.getMainLooper())
    private val sims = ArrayList<Int>()
    private var pendingAction: (() -> Unit)? = null
    private var boundSimId = Int.MIN_VALUE
    private var suppressChecks = false

    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { result ->
        val granted = result.values.all { it }
        if (granted) {
            loadSims()
            pendingAction?.invoke()
        } else {
            GatewayLog.add("SMS and phone permissions are required.")
        }
        pendingAction = null
        showLog()
    }

    private val showLogTick: Runnable = Runnable {
        showLog()
        if (!isFinishing) {
            handler.postDelayed(showLogTick, 1000)
        }
    }

    private fun showLog() {
        if (isFinishing || !::binding.isInitialized) {
            return
        }
        binding.logText.text = GatewayLog.text()
        binding.statusText.text = if (GatewayService.isRunning()) {
            "Running · ${prefs.intervalMinutes} min · " + runningSummary().ifBlank { "no client" }
        } else {
            "Stopped"
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)
        prefs = GatewayPrefs(this)

        binding.serverUrl.setText(prefs.serverUrl.ifBlank { GatewayPrefs.DEFAULT_URL })
        binding.apiKey.setText(prefs.apiKey.ifBlank { GatewayPrefs.DEFAULT_API_KEY })
        val intervals = listOf("1 min", "2 min", "3 min", "4 min", "5 min")
        binding.intervalSpinner.adapter = ArrayAdapter(
            this,
            android.R.layout.simple_spinner_dropdown_item,
            intervals
        )
        binding.intervalSpinner.setSelection(prefs.intervalMinutes - 1)
        binding.intervalSpinner.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: android.view.View?, position: Int, id: Long) {
                prefs.intervalMinutes = position + 1
                showLog()
            }

            override fun onNothingSelected(parent: AdapterView<*>?) = Unit
        }

        binding.simSpinner.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: android.view.View?, position: Int, id: Long) {
                val newId = sims.getOrElse(position) { -1 }
                if (boundSimId != Int.MIN_VALUE && boundSimId != newId) {
                    prefs.setClientsForSim(boundSimId, checkedNames())
                }
                boundSimId = newId
                applyStoredChecks()
            }

            override fun onNothingSelected(parent: AdapterView<*>?) = Unit
        }
        binding.loadClientsButton.setOnClickListener { loadClients() }
        binding.startButton.setOnClickListener { runWithPermissions { startGateway(false) } }
        binding.pollButton.setOnClickListener { runWithPermissions { startGateway(true) } }
        binding.stopButton.setOnClickListener {
            saveFields()
            prefs.running = false
            GatewayService.stop(this)
            GatewayLog.add("Stopped")
            showLog()
        }
        binding.batteryButton.setOnClickListener { askBatteryExemption() }
        loadSims()
        loadClients()
        requestSimIdentityPermissions()
    }

    override fun onResume() {
        super.onResume()
        handler.post(showLogTick)
    }

    override fun onPause() {
        handler.removeCallbacks(showLogTick)
        super.onPause()
    }

    private fun runWithPermissions(action: () -> Unit) {
        val missing = requiredPermissions().filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }
        if (missing.isEmpty()) {
            loadSims()
            action()
            return
        }
        pendingAction = action
        permissionLauncher.launch(missing.toTypedArray())
    }

    private fun requiredPermissions(): List<String> {
        val permissions = mutableListOf(
            Manifest.permission.SEND_SMS,
            Manifest.permission.READ_PHONE_STATE,
            Manifest.permission.READ_PHONE_NUMBERS
        )
        if (Build.VERSION.SDK_INT >= 33) {
            permissions.add(Manifest.permission.POST_NOTIFICATIONS)
        }
        return permissions
    }

    private fun startGateway(pollNow: Boolean) {
        saveFields()
        val routes = prefs.routesFor(sims)
        if (routes.isEmpty()) {
            GatewayLog.add("অন্তত একটা সিমে একটা ক্লায়েন্ট সিলেক্ট করুন।")
            showLog()
            return
        }
        prefs.running = true
        GatewayService.start(this, pollNow)
        PushBridge.sync(this)
        GatewayLog.add("Started. Interval ${prefs.intervalMinutes} min. " + runningSummary())
        showLog()
    }

    private fun runningSummary(): String {
        return prefs.routesFor(sims).joinToString(" | ") { route ->
            val index = sims.indexOf(route.subscriptionId)
            val sim = if (index >= 0) "SIM ${index + 1}" else "SIM"
            sim + ": " + route.clients.joinToString(", ")
        }
    }

    private fun saveFields() {
        prefs.serverUrl = binding.serverUrl.text?.toString().orEmpty()
        prefs.apiKey = binding.apiKey.text?.toString().orEmpty()
        val simId = currentSimId()
        prefs.subscriptionId = simId
        prefs.setClientsForSim(simId, checkedNames())
        PushBridge.sync(this)
    }

    private fun currentSimId(): Int {
        return sims.getOrElse(binding.simSpinner.selectedItemPosition) { -1 }
    }

    private fun checkedNames(): List<String> {
        val names = ArrayList<String>()
        for (index in 0 until binding.clientChecks.childCount) {
            val box = binding.clientChecks.getChildAt(index) as CheckBox
            if (box.isChecked) {
                names.add(box.text.toString())
            }
        }
        return names
    }

    private fun applyStoredChecks() {
        val selected = prefs.clientsForSim(currentSimId()).toSet()
        suppressChecks = true
        for (index in 0 until binding.clientChecks.childCount) {
            val box = binding.clientChecks.getChildAt(index) as CheckBox
            box.isChecked = selected.contains(box.text.toString())
        }
        suppressChecks = false
    }

    private fun loadClients() {
        val url = binding.serverUrl.text?.toString().orEmpty()
        val key = binding.apiKey.text?.toString().orEmpty()
        binding.loadClientsButton.isEnabled = false
        Thread {
            try {
                val names = ApiClient(url, key).clientList()
                runOnUiThread {
                    renderClients(names)
                    GatewayLog.add("Loaded ${names.size} clients")
                    showLog()
                }
            } catch (error: Exception) {
                GatewayLog.add(error.message ?: "Could not load clients")
                runOnUiThread { showLog() }
            } finally {
                runOnUiThread { binding.loadClientsButton.isEnabled = true }
            }
        }.start()
    }

    private fun renderClients(names: List<String>) {
        val selected = prefs.clientsForSim(currentSimId()).toSet()
        binding.clientChecks.removeAllViews()
        suppressChecks = true
        for (name in names) {
            val box = CheckBox(this)
            box.text = name
            box.isChecked = selected.contains(name)
            box.setOnCheckedChangeListener { _, _ ->
                if (!suppressChecks) {
                    saveFields()
                }
            }
            binding.clientChecks.addView(box)
        }
        suppressChecks = false
    }

    private fun requestSimIdentityPermissions() {
        val missing = listOf(
            Manifest.permission.READ_PHONE_STATE,
            Manifest.permission.READ_PHONE_NUMBERS
        ).filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }
        if (missing.isNotEmpty()) {
            permissionLauncher.launch(missing.toTypedArray())
        }
    }

    private fun loadSims() {
        sims.clear()
        val labels = ArrayList<String>()
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) == PackageManager.PERMISSION_GRANTED) {
            val manager = getSystemService(SubscriptionManager::class.java)
            val list: List<SubscriptionInfo> = try {
                manager?.activeSubscriptionInfoList ?: emptyList()
            } catch (_: SecurityException) {
                emptyList()
            }
            for (info in list) {
                sims.add(info.subscriptionId)
                labels.add(simLabel(manager, info))
            }
        }
        if (labels.isEmpty()) {
            sims.add(-1)
            labels.add("Default SIM")
        }
        binding.simSpinner.adapter = ArrayAdapter(this, android.R.layout.simple_spinner_dropdown_item, labels)
        val saved = prefs.subscriptionId
        val index = sims.indexOf(saved)
        if (index >= 0) {
            binding.simSpinner.setSelection(index)
        }
    }

    private fun simLabel(manager: SubscriptionManager?, info: SubscriptionInfo): String {
        val slot = info.simSlotIndex + 1
        val display = info.displayName?.toString()?.trim().orEmpty()
        val carrier = info.carrierName?.toString()?.trim().orEmpty()
        val name = when {
            display.isNotEmpty() && carrier.isNotEmpty() && !display.equals(carrier, ignoreCase = true) -> "$display · $carrier"
            display.isNotEmpty() -> display
            carrier.isNotEmpty() -> carrier
            else -> "SIM $slot"
        }
        val number = simNumber(manager, info)
        return if (number.isEmpty()) "SIM $slot · $name" else "SIM $slot · $name · $number"
    }

    private fun simNumber(manager: SubscriptionManager?, info: SubscriptionInfo): String {
        if (manager == null) {
            return ""
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_NUMBERS) != PackageManager.PERMISSION_GRANTED) {
            return ""
        }
        val raw = try {
            if (Build.VERSION.SDK_INT >= 33) {
                manager.getPhoneNumber(info.subscriptionId)
            } else {
                @Suppress("DEPRECATION")
                info.number
            }
        } catch (_: SecurityException) {
            ""
        }
        return raw?.trim().orEmpty()
    }

    private fun askBatteryExemption() {
        val manager = getSystemService(PowerManager::class.java)
        if (manager != null && manager.isIgnoringBatteryOptimizations(packageName)) {
            GatewayLog.add("Background running is already allowed.")
            showLog()
            return
        }
        val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS).setData(
            Uri.parse("package:$packageName")
        )
        startActivity(intent)
    }
}
