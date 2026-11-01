package com.a2techno.bulksms

import android.content.Context
import com.google.firebase.messaging.FirebaseMessaging

object FcmPush {
    @JvmStatic
    fun sync(context: Context) {
        val appContext = context.applicationContext
        FirebaseMessaging.getInstance().token.addOnSuccessListener { token ->
            register(appContext, token)
        }.addOnFailureListener { error ->
            GatewayLog.add("Push token failed: " + (error.message ?: "unknown"))
        }
    }

    @JvmStatic
    fun register(context: Context, token: String) {
        val prefs = GatewayPrefs(context)
        if (prefs.serverUrl.isBlank() || prefs.apiKey.isBlank()) {
            return
        }
        val clients = prefs.allClients()
        Thread {
            try {
                ApiClient(prefs.serverUrl, prefs.apiKey).registerDevice(token, clients)
                val label = clients.joinToString(", ").ifBlank { "no client" }
                GatewayLog.add("Push ready for $label")
            } catch (error: Exception) {
                GatewayLog.add("Push register failed: " + (error.message ?: "unknown"))
            }
        }.start()
    }
}
