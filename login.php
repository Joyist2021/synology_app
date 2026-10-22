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
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    min-height:100vh;
    font:14px/1.4 "Microsoft YaHei", Arial, sans-serif;
    display:flex; align-items:center; justify-content:center;
    background:#061226;
    padding:20px;
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
  .login-box {
    width:360px;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(120,180,255,.3);
    border-radius:12px;
    padding:30px 26px;
    box-shadow:0 8px 32px rgba(0,20,60,.3);
    color:#e6f1ff;
  }
  .login-box h2 {
    text-align:center; font-weight:600; margin-bottom:20px;
    background:linear-gradient(90deg, #7cc4ff, #4ad6ff);
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }
  .form-group { margin-bottom:14px; }
  .form-group label { display:block; margin-bottom:5px; color:#b8d4f0; font-size:13px; }
  .form-control {
    width:100%; padding:10px 12px;
    background:rgba(0,20,50,.55); color:#fff;
    border:1px solid rgba(120,180,255,.4); border-radius:6px;
    font-size:14px;
  }
  .form-control:focus { outline:none; border-color:#4ad6ff; box-shadow:0 0 0 2px rgba(74,214,255,.2); }
  .btn {
    width:100%; padding:10px; border-radius:6px;
    background:transparent;
    border:1px solid rgba(140,210,255,.5);
    color:#dceeff; font-size:15px; cursor:pointer;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.1);
    transition:all .2s;
  }
  .btn:hover { background:rgba(120,190,255,.9); color:#fff; border-color:rgba(160,220,255,.9); }
  .alert {
    padding:9px 12px; border-radius:6px; margin-bottom:14px;
    background:rgba(217,83,79,.2); border:1px solid rgba(255,150,150,.4);
    color:#ffc8c8; font-size:13px;
  }
  .back { text-align:center; margin-top:14px; font-size:12px; }
  .back a { color:#9fc3e8; text-decoration:none; }
  .back a:hover { color:#fff; }
</style>
</head>
<body>
<div id="bg-slides">
  <div class="slide active" style="background-image:url('lib/bg1.jpg')"></div>
  <div class="slide" style="background-image:url('lib/bg2.jpg')"></div>
  <div class="slide" style="background-image:url('lib/bg3.jpg')"></div>
</div>
<div id="bg-overlay"></div>

<div class="login-box">
  <h2>⚙ 后台登录</h2>
  <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <div class="form-group">
      <label>用户名</label>
      <input class="form-control" name="username" required autofocus>
    </div>
    <div class="form-group">
      <label>密码</label>
      <input type="password" class="form-control" name="password" required>
    </div>
    <button class="btn" type="submit">登 录</button>
    <div class="back"><a href="index.php">← 返回前台</a></div>
  </form>
</div>
<script>
// 原生JS轮播，不依赖jQuery
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
