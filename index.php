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
<link rel="stylesheet" href="lib/jquery.dataTables.min.css">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    background:#74b1d1;
    color:#000;
    font:14px/1.3 Arial, "Microsoft YaHei", sans-serif;
    padding:10px;
  }
  .wrap { max-width:1100px; margin:0 auto; }
  .topbar {
    background:#212121; color:#fff; padding:12px 18px;
    border-radius:6px 6px 0 0; display:flex; justify-content:space-between; align-items:center;
  }
  .topbar h1 { font-size:20px; font-weight:normal; }
  .topbar a { color:#fff; text-decoration:none; font-size:13px; }
  .topbar a:hover { color:#4da6ff; }

  .panel {
    background:#fff; padding:10px; border-radius:0 0 6px 6px;
    box-shadow:0 2px 6px rgba(0,0,0,.2);
  }

  table.dataTable { width:100% !important; border-collapse:collapse; }
  table.dataTable th, table.dataTable td {
    border:1px solid #ddd; padding:6px 8px; text-align:center;
  }
  table.dataTable thead th { background:#f5f5f5; font-weight:bold; }
  table.dataTable td.left { text-align:left; }

  /* 版本按钮：原站风格的蓝色大圆角按钮 */
  .pkg-btn {
    display:inline-block; width:23.5%; margin:2px 0.5%;
    padding:8px 4px; text-align:center;
    background:#337ab7; color:#fff !important;
    border-radius:6px; text-decoration:none; font-size:13px;
    border:1px solid #286090;
  }
  .pkg-btn:hover { background:#286090; }
  .header-btn {
    display:block; width:100%; padding:8px; text-align:center;
    background:#337ab7; color:#fff !important;
    border-radius:6px; text-decoration:none; font-size:13px;
  }
  .header-btn:hover { background:#286090; }

  /* DataTables 控件文字 */
  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dataTables_info,
  .dataTables_wrapper .dataTables_paginate { padding:8px 4px; }
  .dataTables_wrapper .dataTables_filter input { margin-left:6px; padding:4px 8px; border:1px solid #ccc; }
  .dataTables_wrapper .dataTables_length select { padding:3px 6px; margin:0 4px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <h1>群晖NAS架构查询</h1>
    <a href="admin.php"><?= is_admin() ? '后台管理' : '登录后台' ?></a>
  </div>
  <div class="panel">
    <table id="nasTable" class="display">
      <thead>
        <tr>
          <th style="width:120px;">架构</th>
          <th style="width:140px;">细分架构</th>
          <th style="width:150px;">型号</th>
          <th>
            <a class="header-btn" target="_blank" href="https://archive.synology.com/download">官网全部套件及客户端</a>
          </th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $r):
    $versions = json_decode($r['versions'], true) ?: [];
?>
        <tr>
          <td><?= e($r['arch']) ?></td>
          <td><?= e($r['sub_arch']) ?></td>
          <td><strong><?= e($r['model']) ?></strong></td>
          <td class="left">
<?php foreach ($versions as $v): ?>
            <a class="pkg-btn" target="_blank"
               href="<?= e(model_download_url($r['model'], $v)) ?>"><?= e($v) ?>套件</a>
<?php endforeach; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="lib/jquery.min.js"></script>
<script src="lib/jquery.dataTables.min.js"></script>
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
