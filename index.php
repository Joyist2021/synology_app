<?php
require __DIR__ . '/config.php';

$pdo = db();
$rows = $pdo->query(
    'SELECT arch, sub_arch, model, versions FROM nas_models ORDER BY arch, sub_arch, model'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>群晖NAS架构查询</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs/css/dataTables.bootstrap.min.css">
<style>
  body { background:#74b1d1; padding-bottom:30px; }
  .page-header { background:#212121; color:#fff; padding:15px 20px; margin:0 0 15px; }
  .page-header h1 { margin:0; font-size:22px; font-weight:normal; display:inline-block; }
  .page-header .admin-link { color:#fff; float:right; line-height:32px; }
  .container { background:#fff; border-radius:8px; padding:15px; }
  table.dataTable { width:100% !important; }
  table.dataTable td.arch, table.dataTable td.subarch, table.dataTable td.model { text-align:center; white-space:nowrap; }
  .pkg-btn { margin:1px 0; }
  /* 版本按钮按原站风格：一行4个，宽按钮 */
  .pkg-btn { display:inline-block; width:24%; margin:2px 0.5%; text-align:center; }
</style>
</head>
<body>
<div class="page-header">
  <div class="container" style="background:transparent;padding:0;">
    <h1>群晖NAS架构查询</h1>
    <a class="admin-link" href="admin.php"><?= is_admin() ? '后台管理' : '登录后台' ?></a>
  </div>
</div>

<div class="container">
  <table id="nasTable" class="table table-bordered table-striped table-hover">
    <thead>
      <tr>
        <th style="width:140px;">架构</th>
        <th style="width:140px;">细分架构</th>
        <th style="width:160px;">型号</th>
        <th>
          <a class="btn btn-primary btn-sm btn-block" target="_blank"
             href="https://archive.synology.com/download">官网全部套件及客户端</a>
        </th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($rows as $r):
    $versions = json_decode($r['versions'], true) ?: [];
?>
      <tr>
        <td class="arch"><?= e($r['arch']) ?></td>
        <td class="subarch"><?= e($r['sub_arch']) ?></td>
        <td class="model"><strong><?= e($r['model']) ?></strong></td>
        <td>
<?php foreach ($versions as $v): ?>
          <a class="btn btn-primary pkg-btn" target="_blank"
             href="<?= e(model_download_url($r['model'], $v)) ?>"><?= e($v) ?>套件</a>
<?php endforeach; ?>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
<script>
$(function () {
  $('#nasTable').DataTable({
    order: [[0, 'asc'], [2, 'asc']],
    stateSave: true,
    paging: true,
    pagingType: 'simple_numbers',
    pageLength: 10,
    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
    language: {
      sSearch: '群晖型号/架构：',
      sLengthMenu: '显示 _MENU_ 条',
      sInfo: '显示 _START_ 到 _END_，共 _TOTAL_ 条',
      sInfoEmpty: '显示 0 到 0，共 0 条',
      sInfoFiltered: '（从 _MAX_ 条中筛选）',
      sZeroRecords: '没有找到匹配的记录',
      sEmptyTable: '暂无数据',
      oPaginate: { sPrevious: '上一页', sNext: '下一页' }
    }
  });
});
</script>
</body>
</html>
