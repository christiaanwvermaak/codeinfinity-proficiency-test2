<?php
// Simple frontend form to ask for number of persons
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate CSV</title>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <?php include __DIR__ . '/_nav.php'; ?>

    <div style="padding-top:18px;max-width:720px;margin:18px auto;">
  <form id="generateForm" class="card" action="generate.php" method="post" onsubmit="return onSubmitForm()">
        <h1>Generate CSV</h1>
        <p class="lead">Enter how many persons you want to generate and download as a CSV file.</p>

        <label for="count">Please enter the number of persons to generate the CSV file for:</label>
        <input type="number" id="count" name="count" min="1" max="4000000" value="10" required>
        <div class="note">Max 4,000,000. Each generated row contains: ID, Name, Surname, Initials, Age, DateOfBirth.</div>

        <div class="actions">
          <button type="submit" class="btn primary">Generate CSV</button>
          <button type="button" class="btn ghost" onclick="document.getElementById('count').value=100">Reset</button>
        </div>

        <div class="footer">CSV will be generated on the server and downloaded to your browser.</div>
  </form>
  <div id="resultArea" style="margin-top:18px;"></div>

      
    </div>

    <div id="loadingOverlay" class="loading-overlay" role="status" aria-hidden="true">
      <div class="loading-box">
        <div class="spinner" aria-hidden="true"></div>
        <div class="loading-text" id="loadingText">Generating CSV — this may take a while...</div>
      </div>
    </div>

    <script>
      function validateForm(){
        const v = Number(document.getElementById('count').value || 0);
        if(!Number.isInteger(v) || v < 1 || v > 4000000){
          alert('Please enter an integer between 1 and 4000000');
          return false;
        }
        return true;
      }

      function onSubmitForm(){
        if (!validateForm()) return false;
        // show overlay and disable buttons to prevent double submits
        const overlay = document.getElementById('loadingOverlay');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
        document.querySelectorAll('button').forEach(b=>b.disabled = true);
          // instead of submitting, create job via AJAX and run worker
          createAndStartJob();
          return false;
      }

        async function createAndStartJob(){
          const count = Number(document.getElementById('count').value || 0);
          const resultArea = document.getElementById('resultArea');
          const loadingText = document.getElementById('loadingText');
          loadingText.textContent = 'Queuing job...';

          // create job
          const form = new FormData();
          form.append('count', String(count));
          let createResp;
          try {
            createResp = await fetch('create_job.php', { method: 'POST', body: form });
            createResp = await createResp.json();
          } catch (err) {
            loadingText.textContent = 'Failed to create job: ' + err.message;
            document.querySelectorAll('button').forEach(b=>b.disabled = false);
            return;
          }

          if (!createResp.ok) {
            loadingText.textContent = 'Create job failed: ' + (createResp.error || 'unknown');
            document.querySelectorAll('button').forEach(b=>b.disabled = false);
            return;
          }

          loadingText.textContent = 'Starting worker...';

          // start worker
          let startResp;
          try {
            startResp = await fetch('start_job.php', {
              method: 'POST',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({ job: createResp.job })
            });
            startResp = await startResp.json();
          } catch (err) {
            loadingText.textContent = 'Failed to start worker: ' + err.message;
            document.querySelectorAll('button').forEach(b=>b.disabled = false);
            return;
          }

          if (!startResp.ok) {
            loadingText.textContent = 'Worker start failed: ' + (startResp.error || 'unknown');
            document.querySelectorAll('button').forEach(b=>b.disabled = false);
            return;
          }

          loadingText.textContent = 'Generating — this may take a while...';

          // poll for file
          const outUrl = createResp.out_url;
          while (true) {
            try {
              const r = await fetch(outUrl, { method: 'HEAD' });
              if (r.status === 200) break;
            } catch (e) {}
            await new Promise(r=>setTimeout(r, 1500));
          }

          // show download
          loadingText.textContent = 'Generation complete';
          resultArea.innerHTML = '<div class="card"><h2>Download</h2><p><a class="btn primary" href="' + createResp.out_url + '" download>Download CSV</a></p><p>Log: <a href="' + createResp.job_web + '.log">View log</a></p></div>';
          document.querySelectorAll('button').forEach(b=>b.disabled = false);
        }

      // upload handling moved to /upload_page.php
    </script>
  </body>
</html>