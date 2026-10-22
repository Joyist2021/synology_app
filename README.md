# 群晖NAS架构查询 - PHP + SQLite 版

由原静态页 `synology.html` 改造而来，所有 NAS 型号数据已从静态 HTML 迁移到 SQLite，
前台动态查询，后台可直接增删改。

## 目录结构

```
synology_app/
├── index.php          # 前台查询页（动态表格 + 搜索/排序/分页）
├── admin.php          # 后台管理（型号增删改查）
├── login.php          # 后台登录
├── logout.php
├── config.php         # 公共配置（DB、会话、首次自动建表+导入种子数据）
├── data/
│   ├── seed.json      # 从原 HTML 解析出的 224 条初始数据
│   └── synology.db    # SQLite 数据库（首次访问自动生成）
└── parse_html.py      # 一次性解析脚本（已完成，可留作参考）
```

## 部署要求

- PHP ≥ 7.4（需启用 `pdo_sqlite` 扩展，Windows 官方 php 包默认已开）
- 无需 MySQL，单文件数据库

## 安装 / 运行

1. 把整个 `synology_app/` 目录放到 Web 服务器（Nginx / Apache）或直接用 PHP 内置服务器：

   ```bash
   cd C:\scode\synology_app
   php -S 0.0.0.0:8080
   ```

2. 浏览器访问：
   - 前台：http://localhost:8080/index.php
   - 后台：http://localhost:8080/admin.php （会自动跳到登录页）

3. 首次访问 `index.php` 或 `admin.php` 时，`config.php` 会自动：
   - 创建 `data/synology.db`
   - 建表 `nas_models`
   - 从 `data/seed.json` 导入全部 222 条型号

## 后台账号

默认（在 `config.php` 顶部修改）：

- 用户名：`admin`
- 密码：`admin123`

**上线前务必修改 `ADMIN_USER` / `ADMIN_PASS` 常量。**

## 数据库表结构

```sql
CREATE TABLE nas_models (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    arch       TEXT NOT NULL,          -- 架构，如 armada370 / x86_64
    sub_arch   TEXT NOT NULL,          -- 细分架构，如 avoton / broadwell
    model      TEXT NOT NULL,          -- 型号，如 DS218+ / RS214
    versions   TEXT NOT NULL DEFAULT '[]',  -- JSON 数组，如 ["6.0","6.1","7.2"]
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (arch, sub_arch, model)
);
```

前台版本下载链接按固定规则拼接：

```
https://www.synology.com/en-global/support/download/{MODEL}?version={VER}#packages
```

## 后台功能

- 列表：分页、搜索、按列排序（DataTables）
- 新增：填写架构 / 细分架构 / 型号，勾选可用 DSM 版本（6.0 / 6.1 / 6.2 / 7.0 / 7.1 / 7.2）
- 编辑：点"编辑"按钮修改任意字段
- 删除：点"删除"按钮（带确认）
- 同一型号在不同架构下可以存在多行（原站即如此，例如 RS214 在 armada370 和 armadaxp 分类下都有）
