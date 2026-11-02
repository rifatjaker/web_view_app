package com.a2techno.bulksms

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.telephony.SubscriptionManager
import androidx.core.content.ContextCompat
import org.json.JSONArray
import org.json.JSONObject

object SimAssign {
    fun sync(context: Context) {
        val appContext = context.applicationContext
        val prefs = GatewayPrefs(appContext)
        if (prefs.serverUrl.isBlank() || prefs.apiKey.isBlank()) {
            return
        }
        Thread {
            try {
                val assignments = JSONArray()
                for (subscriptionId in activeSubscriptionIds(appContext)) {
                    val number = SimNumbers.number(appContext, subscriptionId)
                    val clients = prefs.clientsForSim(subscriptionId)
                    if (number.isBlank()) {
                        if (clients.isNotEmpty()) {
                            GatewayLog.add("SIM number missing. client_list not updated for " + clients.joinToString(", "))
                        }
                        continue
                    }
                    assignments.put(
                        JSONObject()
                            .put("sim_number", number)
                            .put("client_names", JSONArray(clients))
                    )
                }
                if (assignments.length() == 0) {
                    return@Thread
                }
                ApiClient(prefs.serverUrl, prefs.apiKey).assignSims(assignments)
                GatewayLog.add("Client SIM saved")
            } catch (error: Exception) {
                GatewayLog.add("Client SIM save failed: " + (error.message ?: "unknown"))
            }
        }.start()
    }

    private fun activeSubscriptionIds(context: Context): List<Int> {
        if (ContextCompat.checkSelfPermission(context, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            return emptyList()
        }
        val manager = context.getSystemService(SubscriptionManager::class.java) ?: return emptyList()
        val list = try {
            manager.activeSubscriptionInfoList ?: emptyList()
        } catch (_: SecurityException) {
            emptyList()
        }
        return list.map { it.subscriptionId }
    }
}
