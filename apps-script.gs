// Google Sheets receiver for the Daily Travel Log (static-site version: the page posts here directly).
// Setup: paste into Extensions > Apps Script of the target Google Sheet, then
// Deploy > New deployment > Web app (Execute as: Me, Who has access: Anyone),
// and copy the /exec URL into SUBMIT_URL in index.html.
// After editing this script you must Deploy > Manage deployments > New version.

const SHEET_NAME = "Travel Log";

function doPost(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(10000);
  try {
    const data = JSON.parse(e.postData.contents);
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sh = ss.getSheetByName(SHEET_NAME) || ss.insertSheet(SHEET_NAME);
    if (sh.getLastRow() === 0) sh.appendRow(["Submitted at"].concat(data.header));

    // Column 2 = Trip ID. A retried trip (same ID) is ignored, so no duplicates.
    const tripId = String(data.rows[0][0]);
    const last = sh.getLastRow();
    if (last > 1) {
      const ids = sh.getRange(2, 2, last - 1, 1).getValues().flat().map(String);
      if (ids.indexOf(tripId) !== -1) return ContentService.createTextOutput("duplicate");
    }
    const now = new Date();
    const rows = data.rows.map(r => [now].concat(r));
    sh.getRange(last + 1, 1, rows.length, rows[0].length).setValues(rows);
    return ContentService.createTextOutput("ok");
  } finally {
    lock.releaseLock();
  }
}
