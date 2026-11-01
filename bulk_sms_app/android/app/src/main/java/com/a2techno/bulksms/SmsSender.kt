package com.a2techno.bulksms

import android.app.Activity
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Build
import android.telephony.SmsManager
import java.util.UUID
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicInteger

object SmsSender {
    private val requestCodes = AtomicInteger(1)

    fun send(context: Context, subscriptionId: Int, phone: String, message: String): Boolean {
        val manager = managerFor(context, subscriptionId)
        val parts = manager.divideMessage(message)
        if (parts.isNullOrEmpty()) {
            return false
        }

        val action = "com.a2techno.bulksms.SMS_SENT." + UUID.randomUUID()
        val latch = CountDownLatch(parts.size)
        val failed = AtomicInteger(0)
        val receiver = object : BroadcastReceiver() {
            override fun onReceive(ctx: Context, intent: Intent) {
                if (resultCode != Activity.RESULT_OK) {
                    failed.incrementAndGet()
                }
                latch.countDown()
            }
        }
        val filter = IntentFilter(action)
        if (Build.VERSION.SDK_INT >= 33) {
            context.registerReceiver(receiver, filter, Context.RECEIVER_NOT_EXPORTED)
        } else {
            context.registerReceiver(receiver, filter)
        }

        try {
            val sentIntents = ArrayList<PendingIntent>(parts.size)
            parts.forEachIndexed { index, _ ->
                val intent = Intent(action).setPackage(context.packageName)
                val flags = PendingIntent.FLAG_ONE_SHOT or PendingIntent.FLAG_IMMUTABLE
                sentIntents.add(
                    PendingIntent.getBroadcast(
                        context,
                        requestCodes.incrementAndGet(),
                        intent,
                        flags
                    )
                )
                if (index == 0 && parts.size == 1) {
                    manager.sendTextMessage(phone, null, parts[0], sentIntents[0], null)
                }
            }
            if (parts.size > 1) {
                manager.sendMultipartTextMessage(phone, null, parts, sentIntents, null)
            }
            val finished = latch.await(45, TimeUnit.SECONDS)
            return finished && failed.get() == 0
        } finally {
            try {
                context.unregisterReceiver(receiver)
            } catch (_: Exception) {
            }
        }
    }

    private fun managerFor(context: Context, subscriptionId: Int): SmsManager {
        if (subscriptionId >= 0) {
            try {
                return SmsManager.getSmsManagerForSubscriptionId(subscriptionId)
            } catch (_: Exception) {
            }
        }
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            context.getSystemService(SmsManager::class.java) ?: SmsManager.getDefault()
        } else {
            @Suppress("DEPRECATION")
            SmsManager.getDefault()
        }
    }
}
