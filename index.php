<?php
require __DIR__ . '/config.php';

$pdo = db();

// ====== 分页参数 ======
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');

// ====== 查询 ======
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM nas_models
         WHERE arch LIKE :a OR sub_arch LIKE :s OR model LIKE :m"
    );
    $stmt->execute([':a' => $like, ':s' => $like, ':m' => $like]);
    $total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, arch, sub_arch, model, versions FROM nas_models
         WHERE arch LIKE :a OR sub_arch LIKE :s OR model LIKE :m
         ORDER BY arch, sub_arch, model
         LIMIT $perPage OFFSET :off"
    );
    $off = ($page - 1) * $perPage;
    $stmt->execute([':a' => $like, ':s' => $like, ':m' => $like, ':off' => $off]);
    $rows = $stmt->fetchAll();
} else {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM nas_models")->fetchColumn();
    $off = ($page - 1) * $perPage;
    $rows = $pdo->query(
        "SELECT id, arch, sub_arch, model, versions FROM nas_models
         ORDER BY arch, sub_arch, model
         LIMIT $perPage OFFSET $off"
    )->fetchAll();
}

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$start = $total === 0 ? 0 : $off + 1;
$end = min($off + $perPage, $total);

// 生成分页 URL，保留搜索词
function page_url(int $p): string {
    $q = trim($GLOBALS['q'] ?? '');
    $params = ['page' => $p];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>群晖NAS架构查询</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    min-height:100vh; color:#e6f1ff;
    font:14px/1.4 "Microsoft YaHei", Arial, sans-serif;
    padding:24px 12px 40px;
    background:linear-gradient(180deg, #061226, #081830);
  }
  #bg-slides { position:fixed; inset:0; z-index:-2; overflow:hidden; }
  #bg-slides .slide {
    position:absolute; inset:0; background-size:cover; background-position:center;
    opacity:0; transition:opacity 2s ease-in-out;
  }
  #bg-slides .slide.active { opacity:1; }
  #bg-overlay {
    position:fixed; inset:0; z-index:-1;
    background:linear-gradient(180deg, rgba(6,18,38,.65), rgba(8,24,48,.72));
  }

  .page-title { width:90%; margin:0 auto 12px; text-align:center; }
  .page-title h1 {
    font-size:24px; font-weight:600; letter-spacing:2px;
    background:linear-gradient(90deg, #7cc4ff, #4ad6ff, #7cc4ff);
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }
  .page-title .sub { font-size:12px; color:#8fb4d8; margin-top:4px; }

  .toolbar {
    width:90%; margin:0 auto 10px;
    display:flex; justify-content:space-between; align-items:center;
  }
  .toolbar a { color:#9fc3e8; text-decoration:none; font-size:12px; }
  .toolbar a:hover { color:#fff; }
  .search-form input[type=text] {
    padding:7px 12px; width:280px;
    background:rgba(0,20,50,.55); color:#fff;
    border:1px solid rgba(120,180,255,.4); border-radius:6px;
  }
  .search-form button {
    padding:7px 16px; margin-left:6px;
    background:transparent; color:#dceeff;
    border:1px solid rgba(140,210,255,.5); border-radius:6px; cursor:pointer;
  }
  .search-form button:hover { background:rgba(120,190,255,.9); color:#fff; }

  .table-wrap {
    width:90%; margin:0 auto;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(120,180,255,.25);
    border-radius:10px; padding:14px;
    box-shadow:0 8px 32px rgba(0,20,60,.3);
  }

  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid rgba(120,180,255,.18); padding:10px 8px; text-align:center; }
  thead th {
    background:linear-gradient(180deg, rgba(74,150,255,.25), rgba(74,150,255,.1));
    color:#cfe6ff; border-bottom:2px solid rgba(100,180,255,.4);
  }
  tbody td { background:rgba(255,255,255,.02); color:#dbe9fa; }
  tbody tr:hover td { background:rgba(100,180,255,.12); }

  .pkg-btn {
    display:inline-block; width:10%; margin:2px 0.2%;
    padding:8px 2px; text-align:center;
    background:transparent; color:#dceeff !important;
    border:1px solid rgba(140,210,255,.45); border-radius:5px;
    text-decoration:none; font-size:12px;
    transition:all .2s;
  }
  .pkg-btn:hover { background:rgba(120,190,255,.9); color:#fff; border-color:rgba(160,220,255,.9); }
  .header-btn {
    display:block; width:100%; padding:9px; text-align:center;
    background:transparent; color:#dceeff !important;
    border:1px solid rgba(140,210,255,.45); border-radius:6px;
    text-decoration:none; font-size:14px;
  }

  .pager {
    width:90%; margin:12px auto 0;
    display:flex; justify-content:space-between; align-items:center;
    color:#b8d4f0; font-size:13px;
  }
  .pager .pages a, .pager .pages span {
    display:inline-block; padding:6px 12px; margin:0 2px;
    border:1px solid rgba(120,180,255,.3); border-radius:5px;
    color:#b8d4f0; text-decoration:none; background:rgba(255,255,255,.04);
  }
  .pager .pages a:hover { background:rgba(100,180,255,.2); color:#fff; }
  .pager .pages .current {
    background:linear-gradient(180deg, #3a8fe8, #2568c4);
    color:#fff; border-color:rgba(120,200,255,.6);
  }
  .pager .pages .disabled { opacity:.4; }
</style>
</head>
<body>
<div id="bg-slides">
  <div class="slide active" style="background-image:url('lib/bg1.jpg')"></div>
  <div class="slide" style="background-image:url('lib/bg2.jpg')"></div>
  <div class="slide" style="background-image:url('lib/bg3.jpg')"></div>
</div>
<div id="bg-overlay"></div>

<div class="page-title">
  <h1>☁ 群晖 NAS 架构查询</h1>
  <div class="sub">您可以找到有关 Synology 产品的DSM系统、Packages软件包、Utilities桌面程序、Androids安卓应用等的文档和文件，以便享受最新、多样化的功能。</div>
  <div class="sub">Synology Model / Architecture · Package Download Navigator</div>
</div>

<div class="toolbar">
  <form class="search-form" method="get" action="">
    <input type="text" name="q" placeholder="群晖型号/架构" value="<?= e($q) ?>">
    <button type="submit">搜索</button>
    <?php if ($q !== ''): ?><a href="index.php" style="margin-left:8px;">清除</a><?php endif; ?>
  </form>
  <a href="admin.php"><?= is_admin() ? '后台管理' : '登录后台' ?></a>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th style="width:60px;">ID</th>
        <th style="width:110px;">架构</th>
        <th style="width:130px;">细分架构</th>
        <th style="width:140px;">型号</th>
        <th>
          <a class="header-btn" target="_blank" href="https://archive.synology.com/download">官网全部套件及客户端</a>
        </th>
      </tr>
    </thead>
    <tbody>
<?php if (!$rows): ?>
      <tr><td colspan="5" style="padding:30px;">没有找到匹配的记录</td></tr>
<?php else: foreach ($rows as $r):
    $versions = json_decode($r['versions'], true) ?: [];
?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['arch']) ?></td>
        <td><?= e($r['sub_arch']) ?></td>
        <td><strong><?= e($r['model']) ?></strong></td>
        <td>
<?php foreach ($versions as $v): ?>
          <a class="pkg-btn" target="_blank"
             href="<?= e(model_download_url($r['model'], $v)) ?>"><?= e($v) ?>套件</a>
<?php endforeach;
    if ($versions):
      $latest = end($versions);
      $base = 'https://www.synology.com/en-global/support/download/' . rawurlencode($r['model']) . '?version=' . rawurlencode($latest);
?>
          <div style="margin-top:4px; padding-top:4px; border-top:1px dashed rgba(140,210,255,.25);">
            <a class="pkg-btn" target="_blank" href="<?= e($base) ?>#system">DSM系统</a>
            <a class="pkg-btn" target="_blank" href="<?= e($base) ?>#androids">安卓应用</a>
            <a class="pkg-btn" target="_blank" href="<?= e($base) ?>#utilities">桌面程序</a>
            <a class="pkg-btn" target="_blank" href="<?= e($base) ?>#docs">文档</a>
          </div>
<?php endif; ?>
        </td>
      </tr>
<?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="pager">
  <div>显示 <?= $start ?> 到 <?= $end ?>，共 <?= $total ?> 条</div>
  <div class="pages">
<?php if ($page > 1): ?>
    <a href="<?= e(page_url(1)) ?>">首页</a>
    <a href="<?= e(page_url($page - 1)) ?>">上一页</a>
<?php else: ?>
    <span class="disabled">首页</span>
    <span class="disabled">上一页</span>
<?php endif; ?>

<?php
  // 页码窗口：当前页前后各2页，首尾保留
  $win = 2;
  $startP = max(1, $page - $win);
  $endP = min($totalPages, $page + $win);
  if ($startP > 1) echo '<a href="' . e(page_url(1)) . '">1</a>';
  if ($startP > 2) echo '<span class="disabled">…</span>';
  for ($i = $startP; $i <= $endP; $i++):
?>
    <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
    <?php else: ?><a href="<?= e(page_url($i)) ?>"><?= $i ?></a><?php endif; ?>
<?php endfor;
  if ($endP < $totalPages - 1) echo '<span class="disabled">…</span>';
  if ($endP < $totalPages) echo '<a href="' . e(page_url($totalPages)) . '">' . $totalPages . '</a>';
?>

<?php if ($page < $totalPages): ?>
    <a href="<?= e(page_url($page + 1)) ?>">下一页</a>
    <a href="<?= e(page_url($totalPages)) ?>">末页</a>
<?php else: ?>
    <span class="disabled">下一页</span>
    <span class="disabled">末页</span>
<?php endif; ?>
  </div>
</div>

<script>
// 背景轮播（原生JS，不依赖jQuery）
(function(){
  var slides = document.querySelectorAll('#bg-slides .slide');
  var idx = 0;
  setInterval(function(){
    slides[idx].classList.remove('active');
    idx = (idx + 1) % slides.length;
    slides[idx].classList.add('active');
  }, 6000);
})();
</script>
</body>
</html>
