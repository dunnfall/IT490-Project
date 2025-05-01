<?php require(__DIR__ . "/../partials/nav.php"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Watchlist</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
  <h1 class="mb-4">My Watchlist</h1>

  <!-- Add Ticker -->
  <div class="input-group mb-3">
    <input id="tickerInput" type="text" class="form-control" placeholder="Ticker (e.g. AAPL)">
    <button id="addBtn" class="btn btn-primary">Add</button>
  </div>

  <!-- Table -->
  <table class="table table-striped" id="wlTable">
    <thead><tr><th>Ticker</th><th>Action</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<script>
const API = '/backend';

async function fetchList() {
  const res = await fetch(`${API}/getWatchlist.php`, {credentials:'include'});
  if (!res.ok) { console.error('Failed to load'); return; }
  const list = await res.json();
  const tbody = document.querySelector('#wlTable tbody');
  tbody.innerHTML = '';
  list.forEach(item=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${item.stock_symbol}</td>
      <td><button data-id="${item.id}" class="btn btn-sm btn-danger removeBtn">Remove</button></td>
    `;
    tbody.appendChild(tr);
  });
  document.querySelectorAll('.removeBtn').forEach(btn=>{
    btn.onclick = async ()=>{
      const id = Number(btn.dataset.id);
      await fetch(`${API}/removeFromWatchlist.php`, {
        method:'POST',
        credentials:'include',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id})
      });
      fetchList();
    };
  });
}

document.getElementById('addBtn').onclick = async ()=>{
  const ticker = document.getElementById('tickerInput').value.trim().toUpperCase();
  if (!ticker) return;
  await fetch(`${API}/addToWatchlist.php`, {
    method:'POST',
    credentials:'include',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ticker})
  });
  document.getElementById('tickerInput').value = '';
  fetchList();
};

fetchList();
</script>
</body>
</html>