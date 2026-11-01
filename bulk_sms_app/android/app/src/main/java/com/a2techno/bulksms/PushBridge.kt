package com.a2techno.bulksms

import android.content.Context

object PushBridge {
    fun sync(context: Context) {
        try {
            val cls = Class.forName("com.a2techno.bulksms.FcmPush")
            cls.getMethod("sync", Context::class.java).invoke(null, context.applicationContext)
        } catch (_: ClassNotFoundException) {
            // Push is off until google-services.json is added and the app is rebuilt.
        } catch (error: Exception) {
            GatewayLog.add("Push setup failed: " + (error.cause?.message ?: error.message))
        }
    }
}
