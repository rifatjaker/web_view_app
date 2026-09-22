package com.akb.chemistry;

import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.pdf.PdfRenderer;
import android.os.Bundle;
import android.os.ParcelFileDescriptor;
import android.view.Gravity;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class PdfViewerActivity extends AppCompatActivity {

    public static final String EXTRA_PDF_URL = "pdf_url";
    public static final String EXTRA_COOKIES = "cookies";
    public static final String EXTRA_USER_AGENT = "user_agent";

    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private ProgressBar progressBar;
    private ImageView pageView;
    private TextView pageStatus;
    private Button previousButton;
    private Button nextButton;
    private File cachedPdf;
    private ParcelFileDescriptor fileDescriptor;
    private PdfRenderer renderer;
    private Bitmap pageBitmap;
    private int currentPage;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Prevent screenshots and screen recording in the PDF viewer.
        getWindow().setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE);
        setTitle("Resource Viewer");
        createViewerLayout();

        String pdfUrl = getIntent().getStringExtra(EXTRA_PDF_URL);
        if (pdfUrl == null || !(pdfUrl.startsWith("https://") || pdfUrl.startsWith("http://"))) {
            showError("Invalid PDF link.");
            return;
        }

        loadPdf(pdfUrl);
    }

    private void createViewerLayout() {
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(Color.rgb(45, 45, 45));

        progressBar = new ProgressBar(this);
        LinearLayout.LayoutParams progressParams = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.WRAP_CONTENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
        );
        progressParams.gravity = Gravity.CENTER;
        progressParams.setMargins(0, 24, 0, 24);
        root.addView(progressBar, progressParams);

        pageView = new ImageView(this);
        pageView.setAdjustViewBounds(true);
        pageView.setBackgroundColor(Color.WHITE);
        ScrollView scrollView = new ScrollView(this);
        scrollView.addView(pageView, new ScrollView.LayoutParams(
                ScrollView.LayoutParams.MATCH_PARENT,
                ScrollView.LayoutParams.WRAP_CONTENT
        ));
        root.addView(scrollView, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                0,
                1f
        ));

        LinearLayout controls = new LinearLayout(this);
        controls.setOrientation(LinearLayout.HORIZONTAL);
        controls.setGravity(Gravity.CENTER);
        controls.setPadding(12, 12, 12, 12);

        previousButton = new Button(this);
        previousButton.setText("Previous");
        previousButton.setEnabled(false);
        previousButton.setOnClickListener(v -> renderPage(currentPage - 1));
        controls.addView(previousButton);

        pageStatus = new TextView(this);
        pageStatus.setTextColor(Color.WHITE);
        pageStatus.setGravity(Gravity.CENTER);
        pageStatus.setPadding(24, 0, 24, 0);
        controls.addView(pageStatus, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f));

        nextButton = new Button(this);
        nextButton.setText("Next");
        nextButton.setEnabled(false);
        nextButton.setOnClickListener(v -> renderPage(currentPage + 1));
        controls.addView(nextButton);

        root.addView(controls);
        setContentView(root);
    }

    private void loadPdf(String pdfUrl) {
        String cookies = getIntent().getStringExtra(EXTRA_COOKIES);
        String userAgent = getIntent().getStringExtra(EXTRA_USER_AGENT);

        executor.execute(() -> {
            HttpURLConnection connection = null;
            try {
                connection = (HttpURLConnection) new URL(pdfUrl).openConnection();
                connection.setConnectTimeout(20000);
                connection.setReadTimeout(30000);
                connection.setInstanceFollowRedirects(true);
                if (cookies != null && !cookies.isEmpty()) {
                    connection.setRequestProperty("Cookie", cookies);
                }
                if (userAgent != null && !userAgent.isEmpty()) {
                    connection.setRequestProperty("User-Agent", userAgent);
                }
                connection.connect();

                int status = connection.getResponseCode();
                if (status < 200 || status >= 300) {
                    throw new Exception("Server returned HTTP " + status);
                }

                cachedPdf = File.createTempFile("resource_", ".pdf", getCacheDir());
                try (InputStream input = connection.getInputStream();
                     FileOutputStream output = new FileOutputStream(cachedPdf)) {
                    byte[] buffer = new byte[8192];
                    int read;
                    while ((read = input.read(buffer)) != -1) {
                        output.write(buffer, 0, read);
                    }
                }

                fileDescriptor = ParcelFileDescriptor.open(cachedPdf, ParcelFileDescriptor.MODE_READ_ONLY);
                renderer = new PdfRenderer(fileDescriptor);
                if (renderer.getPageCount() < 1) {
                    throw new Exception("This PDF has no pages.");
                }

                runOnUiThread(() -> {
                    progressBar.setVisibility(ProgressBar.GONE);
                    renderPage(0);
                });
            } catch (Exception e) {
                runOnUiThread(() -> showError("Unable to open PDF for viewing."));
            } finally {
                if (connection != null) {
                    connection.disconnect();
                }
            }
        });
    }

    private void renderPage(int pageNumber) {
        if (renderer == null || pageNumber < 0 || pageNumber >= renderer.getPageCount()) {
            return;
        }

        try (PdfRenderer.Page page = renderer.openPage(pageNumber)) {
            int targetWidth = Math.max(getResources().getDisplayMetrics().widthPixels, page.getWidth());
            float scale = (float) targetWidth / (float) page.getWidth();
            int targetHeight = Math.max(1, Math.round(page.getHeight() * scale));

            if (pageBitmap != null) {
                pageBitmap.recycle();
            }
            pageBitmap = Bitmap.createBitmap(targetWidth, targetHeight, Bitmap.Config.ARGB_8888);
            pageBitmap.eraseColor(Color.WHITE);
            page.render(pageBitmap, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY);
            pageView.setImageBitmap(pageBitmap);

            currentPage = pageNumber;
            int pageCount = renderer.getPageCount();
            pageStatus.setText(String.format(Locale.getDefault(), "%d / %d", currentPage + 1, pageCount));
            previousButton.setEnabled(currentPage > 0);
            nextButton.setEnabled(currentPage < pageCount - 1);
        } catch (Exception e) {
            showError("Unable to display this PDF page.");
        }
    }

    private void showError(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
        finish();
    }

    @Override
    protected void onDestroy() {
        executor.shutdownNow();
        if (pageBitmap != null) {
            pageBitmap.recycle();
            pageBitmap = null;
        }
        if (renderer != null) {
            renderer.close();
            renderer = null;
        }
        if (fileDescriptor != null) {
            try {
                fileDescriptor.close();
            } catch (Exception ignored) {
                // Already closed.
            }
            fileDescriptor = null;
        }
        if (cachedPdf != null && cachedPdf.exists()) {
            cachedPdf.delete();
        }
        super.onDestroy();
    }
}
