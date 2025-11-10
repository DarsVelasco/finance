function addRow(tableId) {
    const table = document.getElementById(tableId);
    const row = table.insertRow(-1);
    const cols = table.rows[1].cloneNode(true);
    row.innerHTML = cols.innerHTML;
  }
  
  function deleteRow(btn) {
    const row = btn.closest("tr");
    const table = row.parentNode;
    if (table.rows.length > 2) {
      row.remove();
    }
  }
  