<?php
require __DIR__ . '/../includes/auth.php';
require_admin();
require __DIR__ . '/../database.php';
$kind = $_GET['type'] ?? 'loans';
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$reportStatuses = [
  'books' => ['Available', 'Checked Out', 'Reserved', 'Under Maintenance', 'Lost'],
  'members' => ['Active', 'Suspended', 'Expired', 'Pending'],
  'loans' => ['Ongoing', 'Returned', 'Overdue', 'Lost']
];
$reports = [
  'books' => ['title' => 'Book inventory', 'headers' => ['ISBN', 'Title', 'Author', 'Genre', 'Year', 'Branch', 'Status'], 'sql' => 'SELECT b.isbn,b.title,b.author_name,b.genre,b.publication_year,br.branch_name,b.status FROM books b JOIN branches br ON br.branch_id=b.allocated_branch_id WHERE (?="" OR b.title LIKE CONCAT("%",?,"%") OR b.author_name LIKE CONCAT("%",?,"%") OR b.isbn LIKE CONCAT("%",?,"%")) AND (?="" OR b.status=?) ORDER BY b.title'],
  'members' => ['title' => 'Member directory', 'headers' => ['Member no.', 'Name', 'Email', 'Type', 'Branch', 'Status', 'Expires'], 'sql' => 'SELECT m.membership_number,CONCAT(m.first_name," ",m.last_name),m.email,m.membership_type,br.branch_name,m.account_status,m.expiration_date FROM members m JOIN branches br ON br.branch_id=m.home_branch_id WHERE (?="" OR m.first_name LIKE CONCAT("%",?,"%") OR m.last_name LIKE CONCAT("%",?,"%") OR m.membership_number LIKE CONCAT("%",?,"%")) AND (?="" OR m.account_status=?) ORDER BY m.last_name,m.first_name'],
  'loans' => ['title' => 'Loan activity', 'headers' => ['Reference', 'Book', 'Member', 'Branch', 'Checkout', 'Due', 'Returned', 'Status', 'Fine'], 'sql' => 'SELECT l.loan_reference,b.title,CONCAT(m.first_name," ",m.last_name),br.branch_name,l.checkout_date,l.due_date,COALESCE(l.return_date,"-"),l.loan_status,l.late_fine_amount FROM book_loans l JOIN books b ON b.book_id=l.book_id JOIN members m ON m.member_id=l.member_id JOIN branches br ON br.branch_id=l.checkout_branch_id WHERE (?="" OR l.loan_reference LIKE CONCAT("%",?,"%") OR b.title LIKE CONCAT("%",?,"%") OR m.last_name LIKE CONCAT("%",?,"%")) AND (?="" OR l.loan_status=?) ORDER BY l.checkout_date DESC']
];
if (!isset($reports[$kind]))
  $kind = 'loans';
$report = $reports[$kind];
if (!in_array($status, $reportStatuses[$kind], true))
  $status = '';
$stmt = mysqli_prepare($conn, $report['sql']);
mysqli_stmt_bind_param($stmt, 'ssssss', $q, $q, $q, $q, $status, $status);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($row = mysqli_fetch_row($result))
  $rows[] = $row;
mysqli_stmt_close($stmt);
if (($_GET['format'] ?? '') === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $kind . '-report-' . date('Y-m-d') . '.csv"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, $report['headers']);
  foreach ($rows as $row)
    fputcsv($out, $row);
  fclose($out);
  exit;
}
if (($_GET['format'] ?? '') === 'pdf') {
  function report_pdf_ascii($value): string
  {
    $value = (string) $value;
    if (function_exists('iconv'))
      $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = preg_replace('/[^\x20-\x7E]/', '', $value);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
  }
  $widths = $kind === 'books' ? [70, 180, 115, 90, 48, 145, 100] : ($kind === 'members' ? [85, 105, 135, 75, 125, 80, 90] : [72, 110, 95, 100, 85, 85, 85, 75, 60]);
  $tableWidth = array_sum($widths);
  $left = 30;
  $tableTop = 540;
  $headerHeight = 25;
  $bottomMargin = 34;
  $availableHeight = $tableTop - $headerHeight - $bottomMargin;
  $preparedRows = [];
  foreach ($rows as $record) {
    $cells = [];
    $maxLines = 1;
    foreach ($report['headers'] as $column => $unused) {
      $value = report_pdf_ascii($record[$column] ?? '');
      $limit = max(5, (int) floor(($widths[$column] - 10) / 4.2));
      $cellLines = explode("\n", wordwrap($value, $limit, "\n", true));
      $cells[] = $cellLines;
      $maxLines = max($maxLines, count($cellLines));
    }
    $preparedRows[] = ['cells' => $cells, 'height' => max(22, 8 + ($maxLines * 10))];
  }
  if (!$preparedRows) {
    $cells = array_fill(0, count($report['headers']), ['']);
    $cells[0] = ['No records found.'];
    $preparedRows[] = ['cells' => $cells, 'height' => 22];
  }
  $pageRows = [];
  $currentRows = [];
  $usedHeight = 0;
  foreach ($preparedRows as $prepared) {
    if ($currentRows && $usedHeight + $prepared['height'] > $availableHeight) {
      $pageRows[] = $currentRows;
      $currentRows = [];
      $usedHeight = 0;
    }
    $currentRows[] = $prepared;
    $usedHeight += $prepared['height'];
  }
  if ($currentRows)
    $pageRows[] = $currentRows;
  $pageCount = count($pageRows);
  $objects = [];
  $pageIds = [];
  $fontRegular = 3 + 2 * $pageCount;
  $fontBold = $fontRegular + 1;
  $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
  foreach ($pageRows as $pageIndex => $pageData) {
    $pageId = 3 + 2 * $pageIndex;
    $contentId = $pageId + 1;
    $pageIds[] = "$pageId 0 R";
    $stream = "0.12 0.22 0.17 rg\nBT /F2 15 Tf 30 570 Td (THE READING ROOM - " . report_pdf_ascii($report['title']) . ") Tj ET\n";
    $pageLabel = 'Generated ' . date('Y-m-d H:i') . '    Page ' . ($pageIndex + 1) . ' of ' . $pageCount;
    $stream .= "0.38 0.43 0.39 rg\nBT /F1 8 Tf 30 555 Td (" . report_pdf_ascii($pageLabel) . ") Tj ET\n";
    $stream .= "0.91 0.94 0.90 rg $left " . ($tableTop - $headerHeight) . " $tableWidth $headerHeight re f\n";
    $x = $left;
    foreach ($report['headers'] as $column => $heading) {
      $label = report_pdf_ascii($heading);
      $stream .= "0.13 0.22 0.17 rg\nBT /F2 8 Tf " . ($x + 5) . ' ' . ($tableTop - 16) . " Td ($label) Tj ET\n";
      $x += $widths[$column];
    }
    $rowTop = $tableTop - $headerHeight;
    foreach ($pageData as $rowIndex => $prepared) {
      $rowHeight = $prepared['height'];
      if ($rowIndex % 2 === 1)
        $stream .= "0.985 0.985 0.97 rg $left " . ($rowTop - $rowHeight) . " $tableWidth $rowHeight re f\n";
      $x = $left;
      foreach ($prepared['cells'] as $column => $cellLines) {
        foreach ($cellLines as $lineIndex => $line) {
          $baseline = $rowTop - 12 - ($lineIndex * 10);
          $stream .= "0.18 0.21 0.19 rg\nBT /F1 8 Tf " . ($x + 5) . ' ' . $baseline . " Td ($line) Tj ET\n";
        }
        $x += $widths[$column];
      }
      $rowTop -= $rowHeight;
    }
    $tableBottom = $rowTop;
    $stream .= "0.72 0.76 0.72 RG 0.55 w\n";
    $y = $tableTop;
    $stream .= "$left $y m " . ($left + $tableWidth) . " $y l S\n";
    $y = $tableTop - $headerHeight;
    $stream .= "$left $y m " . ($left + $tableWidth) . " $y l S\n";
    foreach ($pageData as $prepared) {
      $y -= $prepared['height'];
      $stream .= "$left $y m " . ($left + $tableWidth) . " $y l S\n";
    }
    $x = $left;
    $stream .= "$x $tableTop m $x $tableBottom l S\n";
    foreach ($widths as $width) {
      $x += $width;
      $stream .= "$x $tableTop m $x $tableBottom l S\n";
    }
    $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
  }
  $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . $pageCount . ' >>';
  $objects[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
  $objects[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
  ksort($objects);
  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  foreach ($objects as $id => $object) {
    $offsets[$id] = strlen($pdf);
    $pdf .= "$id 0 obj\n$object\nendobj\n";
  }
  $xref = strlen($pdf);
  $max = max(array_keys($objects));
  $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
  for ($i = 1; $i <= $max; $i++)
    $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
  $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $kind . '-report-' . date('Y-m-d') . '.pdf"');
  header('Content-Length: ' . strlen($pdf));
  echo $pdf;
  exit;
}
$pageTitle = 'Reports & exports';
$basePath = '../';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel">
  <div class="panel-heading">
    <div>
      <div class="eyebrow">LIBRARY INSIGHTS</div>
      <h2>Reports & exports</h2>
      <p>Joined reports from your library records. Search then download a CSV or PDF copy.</p>
    </div>
  </div>
  <form class="filter-bar" method="get"><label>Report<select name="type"
        onchange="this.form.submit()"><?php foreach ($reports as $key => $item): ?>
          <option value="<?= e($key) ?>" <?= $kind === $key ? 'selected' : '' ?>><?= e($item['title']) ?></option><?php endforeach; ?>
      </select></label><label>Search<input type="search" name="q" value="<?= e($q) ?>"
        placeholder="Search report records"></label><label><?= e($kind === 'books' ? 'Book status' : ($kind === 'members' ? 'Member status' : 'Loan status')) ?><select
        name="status">
        <option value="">All statuses</option><?php foreach ($reportStatuses[$kind] as $optionStatus): ?>
          <option value="<?= e($optionStatus) ?>" <?= $status === $optionStatus ? 'selected' : '' ?>><?= e($optionStatus) ?></option>
        <?php endforeach; ?>
      </select></label><button class="button button-outline" type="submit">Apply
      filters</button><?php $exportFilters = ['type' => $kind, 'q' => $q, 'status' => $status, 'format' => 'csv']; ?><a
      class="button button-primary" href="?<?= e(http_build_query($exportFilters)) ?>">Export
      CSV</a><?php $exportFilters['format'] = 'pdf'; ?><a class="button button-outline"
      href="?<?= e(http_build_query($exportFilters)) ?>">Export PDF</a></form>
  <div class="results-line"><strong><?= number_format(count($rows)) ?></strong> records · <?= e($report['title']) ?></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><?php foreach ($report['headers'] as $h): ?>
            <th><?= e(strtoupper($h)) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody><?php foreach (array_slice($rows, 0, 200) as $r): ?>
          <tr><?php foreach ($r as $cell): ?>
              <td><?= e($cell) ?></td><?php endforeach; ?>
          </tr><?php endforeach; ?><?php if (!$rows): ?>
          <tr>
            <td class="empty-state" colspan="<?= count($report['headers']) ?>">No report records found.</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div><?php if (count($rows) > 200): ?>
    <p class="muted">Showing first 200 rows on screen. Exports include all filtered records.</p><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>