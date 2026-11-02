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

data class SendResult(val ok: Boolean, val reason: String = "")

object SmsSender {
    private val requestCodes = AtomicInteger(1)

    fun send(context: Context, subscriptionId: Int, phone: String, message: String): SendResult {
        val manager = managerFor(context, subscriptionId)
        val parts = manager.divideMessage(message)
        if (parts.isNullOrEmpty()) {
            return SendResult(false, "Empty message")
        }

        val action = "com.a2techno.bulksms.SMS_SENT." + UUID.randomUUID()
        val latch = CountDownLatch(parts.size)
        val failed = AtomicInteger(0)
        val reasons = java.util.Collections.synchronizedList(ArrayList<String>())
        val receiver = object : BroadcastReceiver() {
            override fun onReceive(ctx: Context, intent: Intent) {
                if (resultCode != Activity.RESULT_OK) {
                    failed.incrementAndGet()
                    reasons.add(reasonFor(resultCode, intent.getIntExtra("errorCode", 0)))
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
            if (!finished) {
                return SendResult(false, "No send confirmation")
            }
            if (failed.get() == 0) {
                return SendResult(true)
            }
            val text = reasons.distinct().joinToString("; ").ifBlank { "Send failed" }.take(255)
            return SendResult(false, text)
        } finally {
            try {
                context.unregisterReceiver(receiver)
            } catch (_: Exception) {
            }
        }
    }

    private fun reasonFor(resultCode: Int, errorCode: Int): String {
        val base = when (resultCode) {
            SmsManager.RESULT_ERROR_GENERIC_FAILURE -> "Generic failure"
            SmsManager.RESULT_ERROR_RADIO_OFF -> "Radio off"
            SmsManager.RESULT_ERROR_NULL_PDU -> "Empty PDU"
            SmsManager.RESULT_ERROR_NO_SERVICE -> "No service"
            SmsManager.RESULT_ERROR_LIMIT_EXCEEDED -> "Send limit exceeded"
            SmsManager.RESULT_ERROR_FDN_CHECK_FAILURE -> "FDN check failed"
            SmsManager.RESULT_ERROR_SHORT_CODE_NOT_ALLOWED -> "Short code not allowed"
            SmsManager.RESULT_ERROR_SHORT_CODE_NEVER_ALLOWED -> "Short code never allowed"
            SmsManager.RESULT_RADIO_NOT_AVAILABLE -> "Radio not available"
            SmsManager.RESULT_NETWORK_REJECT -> "Network rejected"
            SmsManager.RESULT_INVALID_ARGUMENTS -> "Invalid arguments"
            SmsManager.RESULT_INVALID_STATE -> "Invalid state"
            SmsManager.RESULT_NO_MEMORY -> "No memory"
            SmsManager.RESULT_INVALID_SMS_FORMAT -> "Invalid SMS format"
            SmsManager.RESULT_SYSTEM_ERROR -> "System error"
            SmsManager.RESULT_MODEM_ERROR -> "Modem error"
            SmsManager.RESULT_NETWORK_ERROR -> "Network error"
            SmsManager.RESULT_ENCODING_ERROR -> "Encoding error"
            SmsManager.RESULT_INVALID_SMSC_ADDRESS -> "Invalid SMSC address"
            SmsManager.RESULT_OPERATION_NOT_ALLOWED -> "Operation not allowed"
            SmsManager.RESULT_INTERNAL_ERROR -> "Internal error"
            SmsManager.RESULT_NO_RESOURCES -> "No resources"
            SmsManager.RESULT_CANCELLED -> "Cancelled"
            SmsManager.RESULT_REQUEST_NOT_SUPPORTED -> "Request not supported"
            else -> "Send failed ($resultCode)"
        }
        return if (errorCode == 0) base else "$base (code $errorCode)"
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
