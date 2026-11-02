package com.a2techno.bulksms

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import android.os.IBinder
import android.os.PowerManager
import android.telephony.SubscriptionManager
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import java.util.concurrent.atomic.AtomicBoolean

class GatewayService : Service() {
    private val worker = Thread(::loop, "sms-gateway")
    private val started = AtomicBoolean(false)
    private var lastWaitingStatuses = -1

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val prefs = GatewayPrefs(this)
        startAsForeground(routeLabel(sendRoutes(prefs)))
        if (intent?.action == ACTION_POLL) {
            pollNow.set(true)
        }
        if (started.compareAndSet(false, true)) {
            running.set(true)
            worker.start()
        }
        return START_STICKY
    }

    override fun onDestroy() {
        running.set(false)
        pollNow.set(true)
        super.onDestroy()
    }

    private fun loop() {
        val lock = (getSystemService(POWER_SERVICE) as PowerManager)
            .newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "bulksms:gateway")
        while (running.get()) {
            try {
                lock.acquire(3 * 60 * 1000L)
                pollOnce()
            } catch (error: Exception) {
                GatewayLog.add(error.message ?: "Poll failed")
            } finally {
                if (lock.isHeld) {
                    lock.release()
                }
            }
            var waited = 0
            while (running.get() && !pollNow.get()) {
                val limit = GatewayPrefs(this).intervalSeconds
                if (waited >= limit) {
                    break
                }
                try {
                    Thread.sleep(1000)
                } catch (_: InterruptedException) {
                    break
                }
                waited++
            }
            pollNow.set(false)
        }
        started.set(false)
        stopForeground(STOP_FOREGROUND_REMOVE)
        stopSelf()
    }

    private fun pollOnce() {
        val prefs = GatewayPrefs(this)
        val routes = sendRoutes(prefs)
        val label = routeLabel(routes)
        if (prefs.serverUrl.isBlank() || prefs.apiKey.isBlank() || routes.isEmpty()) {
            GatewayLog.add("অন্তত একটা সিমে একটা ক্লায়েন্ট সিলেক্ট করুন।")
            updateNotification(if (label.isBlank()) "gateway" else label, "No client selected")
            return
        }
        if (!isOnline()) {
            GatewayLog.add("No internet. Will retry.")
            updateNotification(label, "Waiting for internet")
            return
        }

        val api = ApiClient(prefs.serverUrl, prefs.apiKey)
        flushStatuses(api, prefs)

        var sentAny = false
        for (route in routes) {
            if (!running.get()) {
                return
            }
            val simName = simName(route.subscriptionId)
            val batch = try {
                api.getPending(route.clients)
            } catch (error: Exception) {
                GatewayLog.add("$simName: ${error.message}")
                continue
            }
            if (batch.isEmpty()) {
                GatewayLog.add("$simName: no pending SMS")
                continue
            }
            sentAny = true
            GatewayLog.add("$simName: ${batch.size} pending")
            updateNotification(label, "$simName sending ${batch.size}")
            for (sms in batch) {
                if (!running.get()) {
                    return
                }
                val ok = try {
                    SmsSender.send(this, route.subscriptionId, sms.receiver, sms.message)
                } catch (error: Exception) {
                    GatewayLog.add("$simName send error #${sms.id}: ${error.message}")
                    false
                }
                val status = if (ok) "sent" else "failed"
                val sender = SimNumbers.number(this, route.subscriptionId)
                prefs.enqueueStatus(sms.clientName, sms.id, status, sender)
                val from = if (sender.isBlank()) "sender missing" else "from $sender"
                GatewayLog.add("$simName ${status.replaceFirstChar { it.uppercase() }} #${sms.id} ${sms.clientName} to ${sms.receiver} $from (${sms.segments})")
                try {
                    api.updateStatus(sms.clientName, sms.id, status, sender)
                    prefs.removeStatus(sms.clientName, sms.id)
                } catch (error: ApiException) {
                    if (error.httpCode == 404) {
                        prefs.removeStatus(sms.clientName, sms.id)
                    }
                    GatewayLog.add("Status update kept for retry #${sms.id}")
                }
                try {
                    Thread.sleep(1500)
                } catch (_: InterruptedException) {
                    return
                }
            }
        }
        updateNotification(label, if (sentAny) "Waiting" else "No pending SMS")
    }

    private fun sendRoutes(prefs: GatewayPrefs): List<SimRoute> {
        val active = activeSubscriptionIds()
        val ids = if (active.isNotEmpty()) active else listOf(prefs.subscriptionId)
        return prefs.routesFor(ids)
    }

    private fun activeSubscriptionIds(): List<Int> {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            return emptyList()
        }
        val manager = getSystemService(SubscriptionManager::class.java) ?: return emptyList()
        val list = try {
            manager.activeSubscriptionInfoList ?: emptyList()
        } catch (_: SecurityException) {
            emptyList()
        }
        return list.map { it.subscriptionId }
    }

    private fun simName(subscriptionId: Int): String {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            return "SIM"
        }
        val manager = getSystemService(SubscriptionManager::class.java) ?: return "SIM"
        val info = try {
            manager.activeSubscriptionInfoList?.firstOrNull { it.subscriptionId == subscriptionId }
        } catch (_: SecurityException) {
            null
        }
        return if (info == null) "SIM" else "SIM ${info.simSlotIndex + 1}"
    }

    private fun simNumber(subscriptionId: Int): String {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_NUMBERS) != PackageManager.PERMISSION_GRANTED) {
            return ""
        }
        val manager = getSystemService(SubscriptionManager::class.java) ?: return ""
        val raw = try {
            if (Build.VERSION.SDK_INT >= 33) {
                manager.getPhoneNumber(subscriptionId)
            } else {
                @Suppress("DEPRECATION")
                manager.activeSubscriptionInfoList
                    ?.firstOrNull { it.subscriptionId == subscriptionId }
                    ?.number
            }
        } catch (_: SecurityException) {
            ""
        }
        return raw?.filter { it.isDigit() || it == '+' }?.take(20).orEmpty()
    }

    private fun routeLabel(routes: List<SimRoute>): String {
        if (routes.isEmpty()) {
            return "gateway"
        }
        val text = routes.joinToString(" | ") { route ->
            simName(route.subscriptionId) + " " + route.clients.joinToString(", ")
        }
        return if (text.length <= 40) text else text.take(37) + "..."
    }

    private fun flushStatuses(api: ApiClient, prefs: GatewayPrefs) {
        var waiting = 0
        for (item in prefs.pendingStatuses()) {
            try {
                api.updateStatus(item.clientName, item.id, item.status, item.sender)
                prefs.removeStatus(item.clientName, item.id)
            } catch (error: ApiException) {
                if (error.httpCode == 404) {
                    prefs.removeStatus(item.clientName, item.id)
                } else {
                    waiting++
                }
            }
        }
        if (waiting == lastWaitingStatuses) {
            return
        }
        lastWaitingStatuses = waiting
        if (waiting > 0) {
            GatewayLog.add("$waiting status updates waiting to sync")
        }
    }

    private fun isOnline(): Boolean {
        val manager = getSystemService(ConnectivityManager::class.java) ?: return false
        val network = manager.activeNetwork ?: return false
        val capabilities = manager.getNetworkCapabilities(network) ?: return false
        return capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    private fun startAsForeground(clientName: String) {
        val notification = buildNotification(clientName, "Running")
        if (Build.VERSION.SDK_INT >= 29) {
            startForeground(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC)
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }
    }

    private fun updateNotification(clientName: String, detail: String) {
        val manager = getSystemService(NotificationManager::class.java)
        manager.notify(NOTIFICATION_ID, buildNotification(clientName, detail))
    }

    private fun buildNotification(clientName: String, detail: String): Notification {
        val manager = getSystemService(NotificationManager::class.java)
        if (Build.VERSION.SDK_INT >= 26) {
            val channel = NotificationChannel(CHANNEL_ID, "SMS gateway", NotificationManager.IMPORTANCE_LOW)
            manager.createNotificationChannel(channel)
        }
        val open = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_email)
            .setContentTitle("SMS · $clientName")
            .setContentText(detail)
            .setContentIntent(open)
            .setOngoing(true)
            .build()
    }

    companion object {
        const val ACTION_POLL = "com.a2techno.bulksms.POLL"
        private const val CHANNEL_ID = "sms_gateway"
        private const val NOTIFICATION_ID = 41
        private val running = AtomicBoolean(false)
        private val pollNow = AtomicBoolean(false)

        fun start(context: Context, pollNow: Boolean = false) {
            val intent = Intent(context, GatewayService::class.java)
            if (pollNow) {
                intent.action = ACTION_POLL
            }
            context.startForegroundService(intent)
        }

        fun stop(context: Context) {
            running.set(false)
            pollNow.set(true)
            context.stopService(Intent(context, GatewayService::class.java))
        }

        fun isRunning(): Boolean = running.get()
    }
}
