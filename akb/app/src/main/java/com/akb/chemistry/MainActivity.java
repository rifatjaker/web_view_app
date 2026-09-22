package com.akb.chemistry;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.TextView;
import android.widget.Toast;
import androidx.appcompat.app.AppCompatActivity;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private TextView footerDateTime;
    private TextView footerAndroidVersion;
    private Handler handler;
    private Runnable updateDateTimeRunnable;
    private static final String WEBSITE_URL = "https://akbchemistry.com/student_portal/";

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Disable screenshots and screen recording
        getWindow().setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE);
        
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        footerDateTime = findViewById(R.id.footerDateTime);
        footerAndroidVersion = findViewById(R.id.footerAndroidVersion);
        
        // Setup footer information
        setupFooter();
        
        // Configure WebView settings
        WebSettings webSettings = webView.getSettings();
        
        // Enable JavaScript and DOM storage
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);
        
        // Enable cookies for login sessions
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);
        
        // Enable file access (needed for some web apps)
        webSettings.setAllowFileAccess(true);
        webSettings.setAllowContentAccess(true);
        webSettings.setAllowFileAccessFromFileURLs(true);
        webSettings.setAllowUniversalAccessFromFileURLs(true);
        
        // Cache settings
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
        // Note: setAppCacheEnabled is deprecated and removed in API 33+
        // Modern WebView handles caching automatically
        
        // Display settings
        webSettings.setLoadWithOverviewMode(true);
        webSettings.setUseWideViewPort(true);
        webSettings.setBuiltInZoomControls(true);
        webSettings.setDisplayZoomControls(false);
        webSettings.setSupportZoom(true);
        webSettings.setDefaultTextEncodingName("utf-8");
        
        // Enable mixed content for HTTPS sites loading HTTP resources
        webSettings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        
        // User agent (some sites check this)
        webSettings.setUserAgentString(webSettings.getUserAgentString());
        
        // Enable JavaScript interfaces
        webSettings.setJavaScriptCanOpenWindowsAutomatically(true);
        webSettings.setSupportMultipleWindows(false);
        
        // Set WebChromeClient for JavaScript alerts, console, and progress
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                // Progress updates if needed
            }
        });
        
        // Set WebViewClient to handle page navigation
        webView.setWebViewClient(new WebViewClient() {
            @SuppressWarnings("deprecation")
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                if (isPdfUrl(url)) {
                    openPdfInViewer(url);
                    return true;
                }
                view.loadUrl(url);
                return true;
            }
            
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, android.webkit.WebResourceRequest request) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                    String url = request.getUrl().toString();
                    if (isPdfUrl(url)) {
                        openPdfInViewer(url);
                        return true;
                    }
                    view.loadUrl(url);
                }
                return true;
            }
            
            @Override
            public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
            }
            
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                // Ensure JavaScript is fully loaded
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.KITKAT) {
                    view.evaluateJavascript("javascript:void(0);", null);
                }
            }
            
            @Override
            public void onReceivedError(WebView view, android.webkit.WebResourceRequest request, android.webkit.WebResourceError error) {
                super.onReceivedError(view, request, error);
                // Handle errors if needed
            }
        });
        
        // Never save linked files. PDF responses are shown inside the WebView.
        webView.setDownloadListener((url, userAgent, contentDisposition, mimetype, contentLength) -> {
            if (isPdfUrl(url) || (mimetype != null && mimetype.toLowerCase(Locale.US).contains("pdf"))) {
                openPdfInViewer(url);
            } else {
                Toast.makeText(MainActivity.this, "File downloads are disabled.", Toast.LENGTH_SHORT).show();
            }
        });

        // Load the website
        webView.loadUrl(WEBSITE_URL);
    }

    private boolean isPdfUrl(String url) {
        if (url == null) {
            return false;
        }
        String cleanUrl = url.toLowerCase(Locale.US).split("[?#]", 2)[0];
        return cleanUrl.endsWith(".pdf");
    }

    private void openPdfInViewer(String pdfUrl) {
        if (pdfUrl == null || pdfUrl.isEmpty()) {
            return;
        }
        Intent intent = new Intent(this, PdfViewerActivity.class);
        intent.putExtra(PdfViewerActivity.EXTRA_PDF_URL, pdfUrl);
        String cookies = CookieManager.getInstance().getCookie(pdfUrl);
        if (cookies != null) {
            intent.putExtra(PdfViewerActivity.EXTRA_COOKIES, cookies);
        }
        intent.putExtra(PdfViewerActivity.EXTRA_USER_AGENT, webView.getSettings().getUserAgentString());
        startActivity(intent);
    }

    private void setupFooter() {
        // Set Android version
        String androidVersion = "Android " + Build.VERSION.RELEASE + " (API " + Build.VERSION.SDK_INT + ")";
        footerAndroidVersion.setText("Android Version: " + androidVersion);
        
        // Setup datetime updater
        handler = new Handler(Looper.getMainLooper());
        updateDateTimeRunnable = new Runnable() {
            @Override
            public void run() {
                updateDateTime();
                // Update every second
                handler.postDelayed(this, 1000);
            }
        };
        
        // Start updating datetime
        updateDateTime();
        handler.postDelayed(updateDateTimeRunnable, 1000);
    }

    private void updateDateTime() {
        SimpleDateFormat dateFormat = new SimpleDateFormat("EEEE, MMMM dd, yyyy  |  HH:mm:ss", Locale.getDefault());
        String currentDateTime = dateFormat.format(new Date());
        footerDateTime.setText(currentDateTime);
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onDestroy() {
        // Stop datetime updates
        if (handler != null && updateDateTimeRunnable != null) {
            handler.removeCallbacks(updateDateTimeRunnable);
        }
        
        if (webView != null) {
            webView.destroy();
        }
        super.onDestroy();
    }
}

