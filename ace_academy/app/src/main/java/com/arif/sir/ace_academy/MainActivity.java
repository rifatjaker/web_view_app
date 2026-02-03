package com.arif.sir.ace_academy;

import android.annotation.SuppressLint;
import android.graphics.Bitmap;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.WindowManager;
import android.view.animation.Animation;
import android.view.animation.AnimationUtils;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ImageView;
import android.widget.ProgressBar;
import android.widget.TextView;
import androidx.appcompat.app.AppCompatActivity;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import com.google.android.material.snackbar.Snackbar;

import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;

public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private TextView footerDateTime;
    private TextView footerAndroidVersion;
    private ProgressBar progressBar;
    private SwipeRefreshLayout swipeRefreshLayout;
    private View navigationBar;
    private ImageView teacherImage;
    private Handler handler;
    private Runnable updateDateTimeRunnable;
    private static final String WEBSITE_URL = "https://aceacademybd.com/student_portal/";
    private boolean isPageLoaded = false;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Disable screenshots and screen recording
        getWindow().setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE);
        
        setContentView(R.layout.activity_main);

        // Initialize views
        webView = findViewById(R.id.webView);
        footerDateTime = findViewById(R.id.footerDateTime);
        footerAndroidVersion = findViewById(R.id.footerAndroidVersion);
        progressBar = findViewById(R.id.progressBar);
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout);
        navigationBar = findViewById(R.id.navigationBar);
        teacherImage = findViewById(R.id.teacherImage);
        
        // Setup footer information
        setupFooter();
        
        // Setup swipe to refresh
        setupSwipeRefresh();
        
        // Add entrance animation to navigation bar
        Animation slideDown = AnimationUtils.loadAnimation(this, R.anim.slide_down);
        navigationBar.startAnimation(slideDown);
        
        // Add pulse animation to teacher image
        Animation pulse = AnimationUtils.loadAnimation(this, R.anim.pulse);
        teacherImage.startAnimation(pulse);
        
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
        
        // Display settings
        webSettings.setLoadWithOverviewMode(true);
        webSettings.setUseWideViewPort(true);
        webSettings.setBuiltInZoomControls(true);
        webSettings.setDisplayZoomControls(false);
        webSettings.setSupportZoom(true);
        webSettings.setDefaultTextEncodingName("utf-8");
        
        // Enable mixed content for HTTPS sites loading HTTP resources
        webSettings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        
        // User agent
        webSettings.setUserAgentString(webSettings.getUserAgentString());
        
        // Enable JavaScript interfaces
        webSettings.setJavaScriptCanOpenWindowsAutomatically(true);
        webSettings.setSupportMultipleWindows(false);
        
        // Set WebChromeClient for JavaScript alerts, console, and progress
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                // Update progress bar
                if (newProgress < 100) {
                    progressBar.setVisibility(View.VISIBLE);
                    progressBar.setProgress(newProgress);
                } else {
                    progressBar.setVisibility(View.GONE);
                }
            }
            
            @Override
            public void onReceivedTitle(WebView view, String title) {
                super.onReceivedTitle(view, title);
                // You can update the title in the navigation bar if needed
            }
        });
        
        // Set WebViewClient to handle page navigation
        webView.setWebViewClient(new WebViewClient() {
            @SuppressWarnings("deprecation")
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return handleUrlLoading(view, url);
            }
            
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, android.webkit.WebResourceRequest request) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                    return handleUrlLoading(view, request.getUrl().toString());
                }
                return false;
            }
            
            private boolean handleUrlLoading(WebView view, String url) {
                // Check if URL is a PDF file
                if (url != null && (url.toLowerCase().endsWith(".pdf") || url.toLowerCase().contains(".pdf?"))) {
                    // Use Google Docs Viewer to display PDF within WebView
                    String googleDocsUrl = "https://docs.google.com/viewer?url=" + android.net.Uri.encode(url, "UTF-8") + "&embedded=true";
                    view.loadUrl(googleDocsUrl);
                    return true;
                }
                // For non-PDF URLs, load normally
                view.loadUrl(url);
                return true;
            }
            
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                isPageLoaded = false;
                progressBar.setVisibility(View.VISIBLE);
            }
            
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                isPageLoaded = true;
                progressBar.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);
                
                // Ensure JavaScript is fully loaded
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.KITKAT) {
                    view.evaluateJavascript("javascript:void(0);", null);
                }
                
                // Add fade-in animation when page loads
                Animation fadeIn = AnimationUtils.loadAnimation(MainActivity.this, R.anim.fade_in);
                webView.startAnimation(fadeIn);
            }
            
            @Override
            public void onReceivedError(WebView view, android.webkit.WebResourceRequest request, android.webkit.WebResourceError error) {
                super.onReceivedError(view, request, error);
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    String errorMessage = "Error: " + error.getDescription();
                    showErrorMessage(errorMessage);
                }
            }
        });
        
        // Load the website
        webView.loadUrl(WEBSITE_URL);
    }

    private void setupSwipeRefresh() {
        swipeRefreshLayout.setColorSchemeResources(
                R.color.colorPrimary,
                R.color.colorAccent,
                R.color.colorPrimaryDark
        );
        
        swipeRefreshLayout.setOnRefreshListener(new SwipeRefreshLayout.OnRefreshListener() {
            @Override
            public void onRefresh() {
                if (isPageLoaded) {
                    webView.reload();
                } else {
                    swipeRefreshLayout.setRefreshing(false);
                }
            }
        });
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
        
        // Add fade-in animation to footer
        Animation fadeIn = AnimationUtils.loadAnimation(this, R.anim.fade_in);
        findViewById(R.id.footerLayout).startAnimation(fadeIn);
    }

    private void updateDateTime() {
        SimpleDateFormat dateFormat = new SimpleDateFormat("EEEE, MMMM dd, yyyy  |  hh:mm:ss a", Locale.getDefault());
        String currentDateTime = dateFormat.format(new Date());
        footerDateTime.setText(currentDateTime);
    }

    private void showErrorMessage(String message) {
        Snackbar.make(findViewById(android.R.id.content), message, Snackbar.LENGTH_LONG)
                .setAction("RETRY", new View.OnClickListener() {
                    @Override
                    public void onClick(View v) {
                        webView.reload();
                    }
                })
                .show();
    }

    @Override
    protected void onResume() {
        super.onResume();
        // Ensure screenshots and screen recording remain disabled when app resumes
        getWindow().setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE);
        
        // Resume WebView
        if (webView != null) {
            webView.onResume();
            webView.resumeTimers();
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        // Pause WebView
        if (webView != null) {
            webView.onPause();
            webView.pauseTimers();
        }
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
        
        // Destroy WebView
        if (webView != null) {
            webView.destroy();
        }
        super.onDestroy();
    }
}
