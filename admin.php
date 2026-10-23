<?php
require __DIR__ . '/config.php';
require_admin();

$pdo = db();
$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

// ============ 处理 POST（保存 / 删除） ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $arch     = trim($_POST['arch'] ?? '');
        $subArch  = trim($_POST['sub_arch'] ?? '');
        $model    = trim($_POST['model'] ?? '');
        $bayCount = max(0, (int)($_POST['bay_count'] ?? 0));
        $versions = array_values(array_intersect(KNOWN_VERSIONS, array_keys($_POST['versions'] ?? [])));
        usort($versions, fn($a, $b) => version_compare($a, $b));

        if ($arch === '' || $subArch === '' || $model === '') {
            $msg = '架构、细分架构、型号不能为空';
            $action = $id > 0 ? 'edit' : 'new';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE nas_models SET arch=:arch, sub_arch=:sub, model=:model,
                                                bay_count=:bay, versions=:v, updated_at=CURRENT_TIMESTAMP
                         WHERE id=:id'
                    );
                    $stmt->execute([':arch'=>$arch,':sub'=>$subArch,':model'=>$model,
                                    ':bay'=>$bayCount,
                                    ':v'=>json_encode($versions, JSON_UNESCAPED_UNICODE),':id'=>$id]);
                    header('Location: admin.php?msg=' . urlencode('已保存修改'));
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO nas_models (arch, sub_arch, model, bay_count, versions)
                         VALUES (:arch, :sub, :model, :bay, :v)'
                    );
                    $stmt->execute([':arch'=>$arch,':sub'=>$subArch,':model'=>$model,
                                    ':bay'=>$bayCount,
                                    ':v'=>json_encode($versions, JSON_UNESCAPED_UNICODE)]);
                    header('Location: admin.php?msg=' . urlencode('已新增型号'));
                }
                exit;
            } catch (PDOException $ex) {
                $msg = str_contains($ex->getMessage(), 'UNIQUE') ? '该架构+细分架构+型号已存在' : '保存失败：' . $ex->getMessage();
                $action = $id > 0 ? 'edit' : 'new';
            }
        }
    } elseif ($postAction === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $pdo->prepare('DELETE FROM nas_models WHERE id=?')->execute([$id]);
        header('Location: admin.php?msg=' . urlencode('已删除'));
        exit;
    }
}

// 取单条（编辑用）
$item = null;
if ($action === 'edit' && !empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM nas_models WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
    $item = $stmt->fetch() ?: null;
    if (!$item) $action = 'list';
}

// ============ 列表分页查询 ============
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim($_GET['q'] ?? '');
$selArch = trim($_GET['arch'] ?? '');
$selBay  = (int)($_GET['bay'] ?? 0);

$archOptions = $pdo->query("SELECT DISTINCT arch FROM nas_models ORDER BY arch")->fetchAll(PDO::FETCH_COLUMN);
$bayOptions  = $pdo->query("SELECT DISTINCT bay_count FROM nas_models WHERE bay_count > 0 ORDER BY bay_count")->fetchAll(PDO::FETCH_COLUMN);

if ($action === 'list') {
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = "(arch LIKE :a OR sub_arch LIKE :s OR model LIKE :m)";
        $params[':a'] = "%$q%";
        $params[':s'] = "%$q%";
        $params[':m'] = "%$q%";
    }
    if ($selArch !== '') { $where[] = "arch = :arch"; $params[':arch'] = $selArch; }
    if ($selBay > 0)     { $where[] = "bay_count = :bay"; $params[':bay'] = $selBay; }
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $off = ($page-1)*$perPage;
    $st = $pdo->prepare("SELECT COUNT(*) FROM nas_models" . $whereSql);
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $listParams = $params; $listParams[':off'] = $off;
    $st = $pdo->prepare("SELECT * FROM nas_models" . $whereSql . " ORDER BY id LIMIT $perPage OFFSET :off");
    $st->execute($listParams);
    $rows = $st->fetchAll();

    $totalPages = max(1, (int)ceil($total/$perPage));
    if ($page > $totalPages) $page = $totalPages;
    $start = $total === 0 ? 0 : $off+1;
    $end = min($off+$perPage, $total);
}

function admin_page_url(int $p): string {
    $params = ['page' => $p];
    if ($GLOBALS['q'] !== '') $params['q'] = $GLOBALS['q'];
    if ($GLOBALS['selArch'] !== '') $params['arch'] = $GLOBALS['selArch'];
    if ($GLOBALS['selBay'] > 0) $params['bay'] = $GLOBALS['selBay'];
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>后台管理 - 群晖NAS架构查询</title>
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

  .topbar {
    width:90%; margin:0 auto 16px;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(120,180,255,.25);
    border-radius:10px; padding:14px 20px;
    display:flex; justify-content:space-between; align-items:center;
  }
  .topbar h1 { font-size:18px; font-weight:600; color:#cfe6ff; }
  .topbar .links a { color:#9fc3e8; margin-left:15px; text-decoration:none; font-size:13px; }
  .topbar .links a:hover { color:#fff; }

  .container { width:90%; margin:0 auto; }
  .panel {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(120,180,255,.25);
    border-radius:10px; padding:16px;
  }

  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid rgba(120,180,255,.18); padding:9px 8px; text-align:center; }
  thead th {
    background:linear-gradient(180deg, rgba(74,150,255,.25), rgba(74,150,255,.1));
    color:#cfe6ff; border-bottom:2px solid rgba(100,180,255,.4);
  }
  tbody td { background:rgba(255,255,255,.02); color:#dbe9fa; }
  tbody tr:hover td { background:rgba(100,180,255,.12); }

  .btn {
    display:inline-block; padding:6px 14px; border-radius:6px;
    text-decoration:none; font-size:13px; cursor:pointer;
    border:1px solid rgba(140,210,255,.45); color:#dceeff;
    background:transparent;
    transition:all .2s;
  }
  .btn:hover { background:rgba(120,190,255,.9); color:#fff; border-color:rgba(160,220,255,.9); }
  .btn-danger { border-color:rgba(255,150,150,.5); color:#ffd0d0; }
  .btn-danger:hover { background:rgba(217,83,79,.9); color:#fff; }
  .btn-xs { padding:3px 10px; font-size:12px; }

  .alert {
    padding:10px 14px; border-radius:6px; margin-bottom:12px;
    background:rgba(74,150,255,.15); border:1px solid rgba(100,180,255,.4); color:#cfe6ff;
  }
  .alert-error { background:rgba(217,83,79,.18); border-color:rgba(255,150,150,.4); color:#ffd0d0; }

  .toolbar { margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; }
  .search-form input[type=text] {
    padding:7px 12px; width:240px;
    background:rgba(0,20,50,.55); color:#fff;
    border:1px solid rgba(120,180,255,.4); border-radius:6px;
  }
  .search-form button {
    padding:7px 16px; margin-left:6px;
    background:transparent; color:#dceeff;
    border:1px solid rgba(140,210,255,.5); border-radius:6px; cursor:pointer;
  }

  .pager {
    width:100%; margin:12px auto 0;
    display:flex; justify-content:space-between; align-items:center;
    color:#b8d4f0; font-size:13px;
  }
  .pager .pages a, .pager .pages span {
    display:inline-block; padding:5px 11px; margin:0 2px;
    border:1px solid rgba(120,180,255,.3); border-radius:5px;
    color:#b8d4f0; text-decoration:none; background:rgba(255,255,255,.04);
  }
  .pager .pages a:hover { background:rgba(100,180,255,.2); color:#fff; }
  .pager .pages .current { background:linear-gradient(180deg,#3a8fe8,#2568c4); color:#fff; }
  .pager .pages .disabled { opacity:.4; }

  .form-group { margin-bottom:12px; }
  .form-group label { display:block; margin-bottom:5px; color:#b8d4f0; font-size:13px; }
  .form-control {
    width:100%; padding:8px 10px;
    background:rgba(0,20,50,.5); color:#fff;
    border:1px solid rgba(120,180,255,.4); border-radius:5px; font-size:14px;
  }
  .form-control:focus { outline:none; border-color:#4ad6ff; }
  .checkbox-inline { color:#dbe9fa; margin-right:15px; }
  .help-block { color:#8fb4d8; font-size:12px; margin-top:5px; }
</style>
</head>
<body>
<div id="bg-slides">
  <div class="slide active" style="background-image:url('lib/bg1.jpg')"></div>
  <div class="slide" style="background-image:url('lib/bg2.jpg')"></div>
  <div class="slide" style="background-image:url('lib/bg3.jpg')"></div>
</div>
<div id="bg-overlay"></div>

<div class="topbar">
  <h1>⚙ 群晖 NAS · 后台管理</h1>
  <div class="links">
    <a href="index.php" target="_blank">查看前台</a>
    <a href="logout.php">退出登录</a>
  </div>
</div>

<div class="container">

<?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>

<?php if ($action === 'list'): ?>
<div class="panel">
  <div class="toolbar">
    <a class="btn" href="?action=new">+ 新增型号</a>
    <form class="search-form" method="get" action="" style="display:flex;gap:8px;align-items:center;">
      <select name="arch" onchange="this.form.submit()" style="padding:7px 10px;background:rgba(0,20,50,.55);color:#fff;border:1px solid rgba(120,180,255,.4);border-radius:6px;">
        <option value="">全部架构</option>
<?php foreach ($archOptions as $a): ?>
        <option value="<?= e($a) ?>" <?= $selArch===$a?'selected':'' ?>><?= e($a) ?></option>
<?php endforeach; ?>
      </select>
      <select name="bay" onchange="this.form.submit()" style="padding:7px 10px;background:rgba(0,20,50,.55);color:#fff;border:1px solid rgba(120,180,255,.4);border-radius:6px;">
        <option value="0">全部盘位</option>
<?php foreach ($bayOptions as $b): ?>
        <option value="<?= (int)$b ?>" <?= $selBay===(int)$b?'selected':'' ?>><?= (int)$b ?>-bay</option>
<?php endforeach; ?>
      </select>
      <input type="text" name="q" placeholder="搜索型号/架构" value="<?= e($q) ?>">
      <button type="submit">搜索</button>
      <?php if ($q !== '' || $selArch !== '' || $selBay > 0): ?><a class="btn btn-xs" href="admin.php" style="margin-left:4px;">清除</a><?php endif; ?>
    </form>
  </div>
  <table>
    <thead>
      <tr><th style="width:60px;">ID</th><th>架构</th><th>细分架构</th><th>型号</th><th style="width:70px;">盘位</th><th>可用版本</th><th style="width:160px;">操作</th></tr>
    </thead>
    <tbody>
<?php if (!$rows): ?>
      <tr><td colspan="7" style="padding:20px;">没有记录</td></tr>
<?php else: foreach ($rows as $r):
    $vs = json_decode($r['versions'], true) ?: [];
?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['arch']) ?></td>
        <td><?= e($r['sub_arch']) ?></td>
        <td><strong><?= e($r['model']) ?></strong></td>
        <td><?= $r['bay_count'] > 0 ? (int)$r['bay_count'] . '-bay' : '—' ?></td>
        <td><?= e(implode(' / ', $vs)) ?></td>
        <td>
          <a class="btn btn-xs" href="?action=edit&id=<?= (int)$r['id'] ?>">编辑</a>
          <form method="post" style="display:inline;" onsubmit="return confirm('确定删除 <?= e($r['model']) ?>？');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-xs btn-danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
<?php endforeach; endif; ?>
    </tbody>
  </table>

  <div class="pager">
    <div>显示 <?= $start ?> 到 <?= $end ?>，共 <?= $total ?> 条</div>
    <div class="pages">
<?php if ($page > 1): ?>
      <a href="<?= e(admin_page_url(1)) ?>">首页</a>
      <a href="<?= e(admin_page_url($page-1)) ?>">上一页</a>
<?php else: ?>
      <span class="disabled">首页</span><span class="disabled">上一页</span>
<?php endif;
  $win = 2; $sp = max(1,$page-$win); $ep = min($totalPages,$page+$win);
  if ($sp > 1) echo '<a href="'.e(admin_page_url(1)).'">1</a>';
  if ($sp > 2) echo '<span class="disabled">…</span>';
  for ($i=$sp;$i<=$ep;$i++):
?>
      <?php if ($i===$page): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="<?= e(admin_page_url($i)) ?>"><?= $i ?></a><?php endif; ?>
<?php endfor;
  if ($ep < $totalPages-1) echo '<span class="disabled">…</span>';
  if ($ep < $totalPages) echo '<a href="'.e(admin_page_url($totalPages)).'">'.$totalPages.'</a>';
?>
<?php if ($page < $totalPages): ?>
      <a href="<?= e(admin_page_url($page+1)) ?>">下一页</a>
      <a href="<?= e(admin_page_url($totalPages)) ?>">末页</a>
<?php else: ?>
      <span class="disabled">下一页</span><span class="disabled">末页</span>
<?php endif; ?>
    </div>
  </div>
</div>

<?php else:
    $isNew = ($action === 'new');
    $curVersions = $item ? (json_decode($item['versions'], true) ?: []) : [];
?>
<div class="panel" style="max-width:640px;">
  <h3 style="margin-top:0; color:#cfe6ff;"><?= $isNew ? '新增型号' : '编辑型号' ?></h3>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $item ? (int)$item['id'] : 0 ?>">
    <div class="form-group">
      <label>架构（如 armada370 / x86_64）</label>
      <input class="form-control" name="arch" required value="<?= $item ? e($item['arch']) : '' ?>">
    </div>
    <div class="form-group">
      <label>细分架构</label>
      <input class="form-control" name="sub_arch" required value="<?= $item ? e($item['sub_arch']) : '' ?>">
    </div>
    <div class="form-group">
      <label>型号（如 DS218+ / RS214）</label>
      <input class="form-control" name="model" required value="<?= $item ? e($item['model']) : '' ?>">
    </div>
    <div class="form-group">
      <label>盘位（bay count，如 1/2/4/8/12）</label>
      <input class="form-control" name="bay_count" type="number" min="0" max="60" style="max-width:160px;"
             value="<?= $item ? (int)$item['bay_count'] : 0 ?>">
    </div>
    <div class="form-group">
      <label>可用 DSM 版本套件</label>
      <div>
<?php foreach (KNOWN_VERSIONS as $v):
        $checked = in_array($v, $curVersions, true);
?>
        <label class="checkbox-inline">
          <input type="checkbox" name="versions[<?= e($v) ?>]" <?= $checked?'checked':'' ?>>
          <?= e($v) ?> 套件
        </label>
<?php endforeach; ?>
      </div>
      <p class="help-block">未勾选的版本不会在前台生成对应下载按钮。</p>
    </div>
    <hr style="border-color:rgba(120,180,255,.2);margin:14px 0;">
    <button class="btn" type="submit">保存</button>
    <a class="btn" href="admin.php" style="margin-left:8px;">取消</a>
  </form>
</div>
<?php endif; ?>

</div>

<script>
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
