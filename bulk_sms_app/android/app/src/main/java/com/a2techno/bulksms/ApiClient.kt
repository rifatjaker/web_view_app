package com.a2techno.bulksms

import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

class ApiException(val httpCode: Int, message: String) : Exception(message)

data class QueuedSms(
    val id: Int,
    val clientName: String,
    val receiver: String,
    val message: String,
    val segments: Int
)

class ApiClient(serverUrl: String, private val apiKey: String) {
    private val baseUrl = GatewayPrefs.normalizeBase(serverUrl)

    fun clientList(): List<String> {
        val json = post("/api/client_list.php", JSONObject())
        val data = json.optJSONArray("data") ?: return emptyList()
        val names = ArrayList<String>(data.length())
        for (index in 0 until data.length()) {
            val name = data.getJSONObject(index).optString("client_name").trim()
            if (name.isNotEmpty()) {
                names.add(name)
            }
        }
        return names
    }

    fun getPending(clientNames: List<String>): List<QueuedSms> {
        val names = JSONObject().put("client_names", org.json.JSONArray(clientNames))
        val json = post("/api/get_sms.php", names)
        val data = json.optJSONArray("data") ?: return emptyList()
        val items = ArrayList<QueuedSms>(data.length())
        for (index in 0 until data.length()) {
            val row = data.getJSONObject(index)
            items.add(
                QueuedSms(
                    id = row.getInt("id"),
                    clientName = row.getString("client_name"),
                    receiver = row.getString("receiver"),
                    message = row.getString("message"),
                    segments = row.optInt("segments", 1)
                )
            )
        }
        return items
    }

    fun registerDevice(token: String, clientNames: List<String>) {
        val body = JSONObject()
            .put("token", token)
            .put("client_names", org.json.JSONArray(clientNames))
        post("/api/register_device.php", body)
    }

    fun updateStatus(clientName: String, id: Int, status: String, sender: String = "") {
        val body = JSONObject()
            .put("client_name", clientName)
            .put("id", id)
            .put("status", status)
        if (sender.isNotBlank()) {
            body.put("sender", sender)
        }
        post("/api/update_status.php", body)
    }

    private fun post(path: String, body: JSONObject): JSONObject {
        val connection = (URL(baseUrl + path).openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            connectTimeout = 20000
            readTimeout = 30000
            doOutput = true
            setRequestProperty("Content-Type", "application/json; charset=UTF-8")
            setRequestProperty("Accept", "application/json")
            setRequestProperty("X-Api-Key", apiKey)
        }
        try {
            connection.outputStream.use { stream ->
                stream.write(body.toString().toByteArray(Charsets.UTF_8))
            }
            val code = connection.responseCode
            val stream = if (code in 200..299) connection.inputStream else connection.errorStream
            val text = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
            val json = if (text.isBlank()) JSONObject() else JSONObject(text)
            if (code !in 200..299 || json.optString("status") != "success") {
                throw ApiException(code, json.optString("message", "HTTP $code"))
            }
            return json
        } finally {
            connection.disconnect()
        }
    }
}
