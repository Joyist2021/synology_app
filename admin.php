<?php
require __DIR__ . '/config.php';
require_admin();

$pdo = db();
$action = $_GET['action'] ?? 'list';
$msg = '';

// ============ 处理 POST（保存 / 删除） ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $arch     = trim($_POST['arch'] ?? '');
        $subArch  = trim($_POST['sub_arch'] ?? '');
        $model    = trim($_POST['model'] ?? '');
        $versions = array_values(array_intersect(KNOWN_VERSIONS, array_keys($_POST['versions'] ?? [])));
        // 按版本号数值排序
        usort($versions, fn($a, $b) => version_compare($a, $b));

        if ($arch === '' || $subArch === '' || $model === '') {
            $msg = '架构、细分架构、型号不能为空';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE nas_models SET arch=:arch, sub_arch=:sub, model=:model,
                                                versions=:v, updated_at=CURRENT_TIMESTAMP
                         WHERE id=:id'
                    );
                    $stmt->execute([
                        ':arch' => $arch, ':sub' => $subArch, ':model' => $model,
                        ':v' => json_encode($versions, JSON_UNESCAPED_UNICODE), ':id' => $id,
                    ]);
                    $msg = '已保存修改';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO nas_models (arch, sub_arch, model, versions)
                         VALUES (:arch, :sub, :model, :v)'
                    );
                    $stmt->execute([
                        ':arch' => $arch, ':sub' => $subArch, ':model' => $model,
                        ':v' => json_encode($versions, JSON_UNESCAPED_UNICODE),
                    ]);
                    $msg = '已新增型号';
                }
            } catch (PDOException $ex) {
                $msg = '保存失败：' . (str_contains($ex->getMessage(), 'UNIQUE') ? '型号已存在' : $ex->getMessage());
            }
        }
        $action = $id > 0 ? 'edit' : 'new';
        if (!$msg || str_starts_with($msg, '已')) {
            header('Location: admin.php?msg=' . urlencode($msg));
            exit;
        }
    } elseif ($postAction === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM nas_models WHERE id=?')->execute([$id]);
        }
        header('Location: admin.php?msg=' . urlencode('已删除'));
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// ============ 取单条记录（编辑用） ============
$item = null;
if (($action === 'edit') && !empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM nas_models WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
    $item = $stmt->fetch() ?: null;
    if (!$item) {
        $action = 'list';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>后台管理 - 群晖NAS架构查询</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="lib/jquery.dataTables.min.css">
<style>
  body { background:#e9eef2; padding-bottom:30px; }
  .topbar { background:#212121; color:#fff; padding:12px 20px; margin-bottom:15px; }
  .topbar h1 { display:inline-block; margin:0; font-size:18px; font-weight:normal; }
  .topbar a { color:#fff; margin-left:15px; }
  .panel { background:#fff; border-radius:6px; padding:15px; }
</style>
</head>
<body>
<div class="topbar">
  <h1>群晖NAS · 后台管理</h1>
  <a href="index.php" target="_blank">查看前台</a>
  <a href="logout.php">退出登录</a>
</div>

<div class="container">

<?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>

<?php if ($action === 'list'):
    $rows = $pdo->query('SELECT * FROM nas_models ORDER BY arch, sub_arch, model')->fetchAll();
?>
<div class="panel">
  <div style="margin-bottom:10px;">
    <a class="btn btn-primary" href="admin.php?action=new">+ 新增型号</a>
    <span class="text-muted" style="margin-left:10px;">共 <?= count($rows) ?> 条</span>
  </div>
  <table id="listTable" class="table table-bordered table-striped table-hover">
    <thead>
      <tr><th>ID</th><th>架构</th><th>细分架构</th><th>型号</th><th>可用版本</th><th style="width:140px;">操作</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $vs = json_decode($r['versions'], true) ?: [];
    ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['arch']) ?></td>
        <td><?= e($r['sub_arch']) ?></td>
        <td><strong><?= e($r['model']) ?></strong></td>
        <td><?= e(implode(' / ', $vs)) ?></td>
        <td>
          <a class="btn btn-xs btn-default" href="admin.php?action=edit&id=<?= (int)$r['id'] ?>">编辑</a>
          <form method="post" style="display:inline;" onsubmit="return confirm('确定删除 <?= e($r['model']) ?>？');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-xs btn-danger" type="submit">删除</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="lib/jquery.min.js"></script>
<script src="lib/jquery.dataTables.min.js"></script>
<script>
$(function(){
  $('#listTable').DataTable({
    order: [[0,'asc']],
    stateSave: true,
    pagingType: 'simple_numbers',
    pageLength: 10,
    lengthMenu: [[10,25,50,100],[10,25,50,100]],
    language: {
      sSearch:'搜索：', sLengthMenu:'显示 _MENU_ 条',
      sInfo:'显示 _START_ 到 _END_，共 _TOTAL_ 条',
      sInfoEmpty:'显示 0 到 0，共 0 条',
      sZeroRecords:'无记录',
      oPaginate:{sPrevious:'上一页',sNext:'下一页'}
    }
  });
});
</script>

<?php else:
    $isNew = ($action === 'new');
    $curVersions = $item ? (json_decode($item['versions'], true) ?: []) : [];
?>
<div class="panel" style="max-width:640px;">
  <h3 style="margin-top:0;"><?= $isNew ? '新增型号' : '编辑型号' ?></h3>
  <?php if ($msg && !str_starts_with($msg, '已')): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $item ? (int)$item['id'] : 0 ?>">
    <div class="form-group">
      <label>架构（如 armada370 / x86_64）</label>
      <input class="form-control" name="arch" required
             value="<?= $item ? e($item['arch']) : '' ?>">
    </div>
    <div class="form-group">
      <label>细分架构</label>
      <input class="form-control" name="sub_arch" required
             value="<?= $item ? e($item['sub_arch']) : '' ?>">
    </div>
    <div class="form-group">
      <label>型号（如 DS218+ / RS214）</label>
      <input class="form-control" name="model" required
             value="<?= $item ? e($item['model']) : '' ?>">
    </div>
    <div class="form-group">
      <label>可用 DSM 版本套件</label>
      <div>
        <?php foreach (KNOWN_VERSIONS as $v):
            $checked = in_array($v, $curVersions, true);
        ?>
        <label class="checkbox-inline" style="margin-left:0;margin-right:15px;">
          <input type="checkbox" name="versions[<?= e($v) ?>]" <?= $checked ? 'checked' : '' ?>>
          <?= e($v) ?> 套件
        </label>
        <?php endforeach; ?>
      </div>
      <p class="help-block">未勾选的版本不会在前台生成对应下载按钮。</p>
    </div>
    <hr>
    <button class="btn btn-primary" type="submit">保存</button>
    <a class="btn btn-default" href="admin.php">取消</a>
  </form>
</div>
<?php endif; ?>

</div>
</body>
</html>
