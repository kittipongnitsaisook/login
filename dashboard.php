<?php
// dashboard.php

// 1. เรียกใช้ config หลัก (ซึ่งจะเริ่ม session และเชื่อมต่อ $mysqli ให้เราอัตโนมัติ)
require __DIR__ . '/config_mysqli.php';

// 2. ตรวจสอบว่าล็อกอินแล้วหรือยัง (ถ้ายัง ให้เด้งกลับไปหน้า login)
if (empty($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'กรุณาล็อกอินก่อนเข้าสู่ระบบ';
    header('Location: login.php');
    exit; // จบการทำงานทันที
}

// ==========================================================
// ส่วน DEBUGGING
// ==========================================================
$error_message = null; // 3. สร้างตัวแปรไว้เก็บข้อผิดพลาด
// 4. กำหนดค่าเริ่มต้นให้ตัวแปรข้อมูล (ป้องกัน error หากคิวรี่ล้มเหลว)
$monthly = $category = $region = $topProducts = $payment = $hourly = $newReturning = [];
$kpi = ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

/**
 * 5. ปรับปรุงฟังก์ชัน fetch_all ให้โยน Exception เมื่อคิวรี่ล้มเหลว
 */
function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  
  if (!$res) {
    // ถ้าคิวรี่ไม่สำเร็จ ให้โยนข้อผิดพลาดออกมาทันที
    throw new Exception("SQL Query Error: " . $mysqli->error . "\n\n[Query: $sql]");
  }

  $rows = [];
  while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  $res->free();
  return $rows;
}
// ==========================================================

// Helper for number format
function nf($n) { return number_format((float)$n, 2); }

// 6. ล้อมการดึงข้อมูลทั้งหมดด้วย try...catch
//    (เราใช้ตัวแปร $mysqli ตัวเดียวกับที่มาจาก config_mysqli.php)
try {
  
  // เตรียมข้อมูลสำหรับกราฟต่าง ๆ
  $monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
  $category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
  $region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
  $topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
  $payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
  $hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
  $newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
  
  $kpis_query = fetch_all($mysqli, "
    SELECT
      (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
      (SELECT SUM(quantity)   FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
      (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
  ");
  $kpi = $kpis_query ? $kpis_query[0] : $kpi;

} catch (Exception $e) {
  // 7. ถ้ามีข้อผิดพลาดเกิดขึ้น ให้เก็บข้อความไว้
  $error_message = $e->getMessage();
}

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail DW Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    body { background: #0f172a; color: #e2e8f0; }
    .card { background: #111827; border: 1px solid rgba(255,255,255,0.06); border-radius: 1rem; }
    .card h5 { color: #9ca3af; }
    .kpi { font-size: 1.4rem; font-weight: 700; color: #f9fafb; }
    .sub { color: #93c5fd; font-size: .9rem; }
    .grid { display: grid; gap: 1rem; grid-template-columns: repeat(12, 1fr); }
    .col-12 { grid-column: span 12; }
    .col-6 { grid-column: span 6; }
    .col-4 { grid-column: span 4; }
    .col-8 { grid-column: span 8; }
    @media (max-width: 991px) {
      .col-6, .col-4, .col-8 { grid-column: span 12; }
    }
    canvas { max-height: 360px; }
    /* สไตล์สำหรับกล่อง error */
    .alert-danger pre { 
      white-space: pre-wrap; 
      word-break: break-all; 
      color: #721c24;
      font-size: 0.9rem;
      background: #f8d7da;
      padding: 10px;
      border-radius: 4px;
    }
  </style>
</head>
<body class="p-3 p-md-4">
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">ยอดขาย (Retail DW) — Dashboard</h2>
      <div>
        <span class="sub me-3">สวัสดี, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
        <a href="logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
      </div>
    </div>

    <?php if ($error_message): ?>
      <div class="alert alert-danger" role="alert">
        <h4 class="alert-heading">🚫 เกิดข้อผิดพลาดในการดึงข้อมูล</h4>
        <p>ระบบไม่สามารถโหลดข้อมูลแดชบอร์ดได้ สาเหตุที่เป็นไปได้มากที่สุดคือ **ตาราง (Table) หรือ วิว (View) ที่เรียกใช้ไม่มีอยู่จริง**</p>
        <hr>
        <p class="mb-1"><strong>ข้อความทางเทคนิค (สำหรับ Debug):</strong></p>
        <pre><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></pre>
        <small>โปรดตรวจสอบว่าฐานข้อมูล (<?= DB_NAME ?>) ที่ระบุใน <code>config_mysqli.php</code> มี View และ Table ที่จำเป็นครบถ้วน</small>
      </div>
    <?php endif; ?>
    <div class="grid mb-3">
      <div class="card p-3 col-4">
        <h5>ยอดขาย 30 วัน</h5>
        <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
      </div>
      <div class="card p-3 col-4">
        <h5>จำนวนชิ้นขาย 30 วัน</h5>
        <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div>
      </div>
      <div class="card p-3 col-4">
        <h5>จำนวนผู้ซื้อ 30 วัน</h5>
        <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div>
      </div>
    </div>

    <div class="grid">

      <div class="card p-3 col-8">
        <h5 class="mb-2">ยอดขายรายเดือน (2 ปี)</h5>
        <canvas id="chartMonthly"></canvas>
      </div>

      <div class="card p-3 col-4">
        <h5 class="mb-2">สัดส่วนยอดขายตามหมวด</h5>
        <canvas id="chartCategory"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">Top 10 สินค้าขายดี</h5>
        <canvas id="chartTopProducts"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">ยอดขายตามภูมิภาค</h5>
        <canvas id="chartRegion"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">วิธีการชำระเงิน</h5>
        <canvas id="chartPayment"></canvas>
      </div>

      <div class="card p-3 col-6">
        <h5 class="mb-2">ยอดขายรายชั่วโมง</h5>
        <canvas id="chartHourly"></canvas>
      </div>

      <div class="card p-3 col-12">
        <h5 class="mb-2">ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5>
        <canvas id="chartNewReturning"></canvas>
      </div>

    </div>
  </div>

<script>
// 10. ป้องกัน Javascript Error กรณีคิวรี่ล้มเหลว
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });

if (monthly.length > 0) {
  (() => {
    const {labels, values} = toXY(monthly, 'ym', 'net_sales');
    new Chart(document.getElementById('chartMonthly'), {
      type: 'line',
      data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values, tension: .25, fill: true }] },
      options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
        x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
        y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
      }}
    });
  })();
}

if (category.length > 0) {
  (() => {
    const {labels, values} = toXY(category, 'category', 'net_sales');
    new Chart(document.getElementById('chartCategory'), {
      type: 'doughnut',
      data: { labels, datasets: [{ data: values }] },
      options: { plugins: { legend: { position: 'bottom', labels: { color: '#e5e7eb' } } } }
    });
  })();
}

if (topProducts.length > 0) {
  (() => {
    const labels = topProducts.map(o => o.product_name);
    const qty = topProducts.map(o => parseInt(o.qty_sold));
    new Chart(document.getElementById('chartTopProducts'), {
      type: 'bar',
      data: { labels, datasets: [{ label: 'ชิ้นที่ขาย', data: qty }] },
      options: {
        indexAxis: 'y',
        plugins: { legend: { labels: { color: '#e5e7eb' } } },
        scales: {
          x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
          y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
        }
      }
    });
  })();
}

if (region.length > 0) {
  (() => {
    const {labels, values} = toXY(region, 'region', 'net_sales');
    new Chart(document.getElementById('chartRegion'), {
      type: 'bar',
      data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values }] },
      options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
        x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
        y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
      }}
    });
  })();
}

if (payment.length > 0) {
  (() => {
    const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
    new Chart(document.getElementById('chartPayment'), {
      type: 'pie',
      data: { labels, datasets: [{ data: values }] },
      options: { plugins: { legend: { position: 'bottom', labels: { color: '#e5e7eb' } } } }
    });
  })();
}

if (hourly.length > 0) {
  (() => {
    const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
    new Chart(document.getElementById('chartHourly'), {
      type: 'bar',
      data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values }] },
      options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
        x: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } },
        y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
      }}
    });
  })();
}

if (newReturning.length > 0) {
  (() => {
    const labels = newReturning.map(o => o.date_key);
    const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
    const retC = newReturning.map(o => parseFloat(o.returning_sales));
    new Chart(document.getElementById('chartNewReturning'), {
      type: 'line',
      data: { labels,
        datasets: [
          { label: 'ลูกค้าใหม่ (฿)', data: newC, tension: .25, fill: false },
          { label: 'ลูกค้าเดิม (฿)', data: retC, tension: .25, fill: false }
        ]
      },
      options: { plugins: { legend: { labels: { color: '#e5e7eb' } } }, scales: {
        x: { ticks: { color: '#c7d2fe', maxTicksLimit: 12 }, grid: { color: 'rgba(255,255,255,.08)' } },
        y: { ticks: { color: '#c7d2fe' }, grid: { color: 'rgba(255,255,255,.08)' } }
      }}
    });
  })();
}
</script>

</body>
</html>