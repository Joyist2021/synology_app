<?php
require __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
        $_SESSION['is_admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $error = '用户名或密码错误';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>后台登录 - 群晖NAS架构查询</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
<style>body{background:#74b1d1;padding-top:80px;}.login-box{max-width:360px;margin:0 auto;background:#fff;padding:25px;border-radius:8px;}</style>
</head>
<body>
<div class="login-box">
  <h3 class="text-center" style="margin-top:0;">后台登录</h3>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <div class="form-group">
      <label>用户名</label>
      <input class="form-control" name="username" required autofocus>
    </div>
    <div class="form-group">
      <label>密码</label>
      <input type="password" class="form-control" name="password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">登录</button>
    <p style="margin-top:10px;"><a href="index.php">&larr; 返回前台</a></p>
  </form>
</div>
</body>
</html>
