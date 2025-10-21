<?php
// Simple frontend form to ask for number of persons
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate CSV</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <form class="card" action="generate.php" method="post" onsubmit="return validateForm()">
      <h1>Generate CSV</h1>
      <p class="lead">Enter how many persons you want to generate and download as a CSV file.</p>

      <label for="count">Please enter the number of persons to generate the CSV file for:</label>
      <input type="number" id="count" name="count" min="1" max="10000" value="10" required>
      <div class="note">Max 1,000,000. Each generated row contains: id, first_name, last_name, email, age.</div>

      <div class="actions">
        <button type="submit" class="btn primary">Generate CSV</button>
        <button type="button" class="btn ghost" onclick="document.getElementById('count').value=10">Reset</button>
      </div>

      <div class="footer">CSV will be generated on the server and downloaded to your browser.</div>
    </form>

    <script>
      function validateForm(){
        const v = Number(document.getElementById('count').value || 0);
        if(!Number.isInteger(v) || v < 1 || v > 1000000){
          alert('Please enter an integer between 1 and 1000000');
          return false;
        }
        return true;
      }
    </script>
  </body>
</html>