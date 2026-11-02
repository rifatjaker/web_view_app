package com.a2techno.bulksms

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject

class GatewayPrefs(context: Context) {
    private val prefs = context.getSharedPreferences("gateway", Context.MODE_PRIVATE)
    private val queuePrefs = context.getSharedPreferences("status_queue", Context.MODE_PRIVATE)

    var serverUrl: String
        get() = prefs.getString(KEY_URL, DEFAULT_URL) ?: DEFAULT_URL
        set(value) {
            prefs.edit().putString(KEY_URL, normalizeBase(value)).apply()
        }

    var apiKey: String
        get() = prefs.getString(KEY_API, DEFAULT_API_KEY) ?: DEFAULT_API_KEY
        set(value) {
            prefs.edit().putString(KEY_API, value.trim()).apply()
        }

    var subscriptionId: Int
        get() = prefs.getInt(KEY_SIM, -1)
        set(value) {
            prefs.edit().putInt(KEY_SIM, value).apply()
        }

    var running: Boolean
        get() = prefs.getBoolean(KEY_RUNNING, false)
        set(value) {
            prefs.edit().putBoolean(KEY_RUNNING, value).apply()
        }

    var intervalSeconds: Int
        get() = prefs.getInt(KEY_INTERVAL_SECONDS, 10).coerceIn(10, 120)
        set(value) {
            prefs.edit().putInt(KEY_INTERVAL_SECONDS, value.coerceIn(10, 120)).apply()
        }

    fun intervalLabel(): String {
        val seconds = intervalSeconds
        return if (seconds < 60) "$seconds sec" else "${seconds / 60} min"
    }

    fun clientsForSim(subscriptionId: Int): List<String> {
        val array = clientMap().optJSONArray(subscriptionId.toString()) ?: return emptyList()
        val names = ArrayList<String>(array.length())
        for (index in 0 until array.length()) {
            val name = array.optString(index).trim()
            if (name.isNotEmpty()) {
                names.add(name)
            }
        }
        return names
    }

    fun setClientsForSim(subscriptionId: Int, names: List<String>) {
        val map = clientMap()
        val array = JSONArray()
        names.map { it.trim() }.filter { it.isNotEmpty() }.distinct().forEach { array.put(it) }
        map.put(subscriptionId.toString(), array)
        prefs.edit().putString(KEY_CLIENTS, map.toString()).apply()
    }

    fun selectedClients(): List<String> = clientsForSim(subscriptionId)

    fun allClients(): List<String> {
        val map = clientMap()
        val names = ArrayList<String>()
        val keys = map.keys()
        while (keys.hasNext()) {
            val array = map.optJSONArray(keys.next()) ?: continue
            for (index in 0 until array.length()) {
                val name = array.optString(index).trim()
                if (name.isNotEmpty() && !names.contains(name)) {
                    names.add(name)
                }
            }
        }
        return names
    }

    fun routesFor(subscriptionIds: List<Int>): List<SimRoute> {
        return subscriptionIds.mapNotNull { id ->
            val clients = clientsForSim(id)
            if (clients.isEmpty()) null else SimRoute(id, clients)
        }
    }

    fun enqueueStatus(clientName: String, id: Int, status: String, sender: String = "") {
        val items = readQueue()
        val kept = JSONArray()
        for (index in 0 until items.length()) {
            val item = items.getJSONObject(index)
            if (!(item.getInt("id") == id && item.getString("client_name") == clientName)) {
                kept.put(item)
            }
        }
        kept.put(
            JSONObject()
                .put("client_name", clientName)
                .put("id", id)
                .put("status", status)
                .put("sender", sender)
        )
        queuePrefs.edit().putString(KEY_QUEUE, kept.toString()).apply()
    }

    fun pendingStatuses(): List<StatusUpdate> {
        val items = readQueue()
        val list = ArrayList<StatusUpdate>(items.length())
        for (index in 0 until items.length()) {
            val item = items.getJSONObject(index)
            list.add(
                StatusUpdate(
                    item.getString("client_name"),
                    item.getInt("id"),
                    item.getString("status"),
                    item.optString("sender")
                )
            )
        }
        return list
    }

    fun removeStatus(clientName: String, id: Int) {
        val items = readQueue()
        val kept = JSONArray()
        for (index in 0 until items.length()) {
            val item = items.getJSONObject(index)
            if (!(item.getInt("id") == id && item.getString("client_name") == clientName)) {
                kept.put(item)
            }
        }
        queuePrefs.edit().putString(KEY_QUEUE, kept.toString()).apply()
    }

    private fun clientMap(): JSONObject {
        val raw = prefs.getString(KEY_CLIENTS, "{}") ?: "{}"
        return try {
            JSONObject(raw)
        } catch (_: Exception) {
            JSONObject()
        }
    }

    private fun readQueue(): JSONArray {
        val raw = queuePrefs.getString(KEY_QUEUE, "[]") ?: "[]"
        return try {
            JSONArray(raw)
        } catch (_: Exception) {
            JSONArray()
        }
    }

    companion object {
        const val DEFAULT_URL = "https://soft.a2technologiesbd.com/bulk_sms"
        const val DEFAULT_API_KEY = ""

        private const val KEY_URL = "server_url"
        private const val KEY_API = "api_key"
        private const val KEY_CLIENTS = "clients_by_sim"
        private const val KEY_SIM = "subscription_id"
        private const val KEY_RUNNING = "running"
        private const val KEY_INTERVAL_SECONDS = "interval_seconds"
        private const val KEY_QUEUE = "items"

        fun normalizeBase(input: String): String {
            var url = input.trim().trimEnd('/')
            if (url.endsWith("/sms_queue_insert.php")) {
                url = url.removeSuffix("/sms_queue_insert.php")
            }
            if (url.endsWith("/api")) {
                url = url.removeSuffix("/api")
            }
            return url
        }
    }
}

data class StatusUpdate(
    val clientName: String,
    val id: Int,
    val status: String,
    val sender: String = ""
)

data class SimRoute(
    val subscriptionId: Int,
    val clients: List<String>
)
