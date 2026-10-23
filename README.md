# 群晖NAS架构查询 - PHP + SQLite 版

由原静态页 `synology.html` 改造而来，所有 NAS 型号数据已从静态 HTML 迁移到 SQLite，
前台动态查询，后台可直接增删改。

## 目录结构

```
synology_app/
├── index.php          # 前台查询页（服务端分页 + 搜索）
├── admin.php          # 后台管理（型号增删改查）
├── login.php          # 后台登录
├── logout.php
├── config.php        # 公共配置（DB、会话、首次自动建表+导入种子数据）
├── lib/
│   ├── jquery.min.js
│   ├── jquery.dataTables.min.js
│   ├── jquery.dataTables.min.css
│   ├── bg1.jpg        # 机械姬背景图 1
│   ├── bg2.jpg        # 机械姬背景图 2
│   └── bg3.jpg        # 机械姬背景图 3
├── data/
│   ├── seed.json      # 初始型号种子数据（224 条）
│   └── synology.db    # SQLite 数据库（首次访问自动生成）
└── parse_html.py      # 一次性解析脚本（已完成，可留作参考）
```

## 部署要求

- PHP ≥ 7.4（需启用 `pdo_sqlite` 扩展，Windows 官方 php 包默认已开）
- 无需 MySQL，单文件数据库
- 无需外网 CDN，jQuery / DataTables / 背景图全部本地化

## 安装 / 运行

1. 把整个 `synology_app/` 目录放到 Web 服务器（Nginx / Apache）或直接用 PHP 内置服务器：

   ```bash
   cd C:\scode\synology_app
   php -S 0.0.0.0:8080
   ```

2. 浏览器访问：
   - 前台：http://localhost:8080/index.php
   - 后台：http://localhost:8080/admin.php （会自动跳到登录页）

3. 首次访问时 `config.php` 会自动：
   - 创建 `data/synology.db`
   - 建表 `nas_models`
   - 从 `data/seed.json` 导入全部初始型号数据

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
    bay_count  INTEGER NOT NULL DEFAULT 0,  -- 盘位数 1/2/4/5/6/8/12/24
    versions   TEXT NOT NULL DEFAULT '[]',  -- JSON 数组
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (arch, sub_arch, model)
);
```

`versions` 示例：
```json
["4.3","5.0","5.1","5.2","6.0","6.1","6.2","7.0","7.1","7.2"]
```

前台下载链接按固定规则拼接：

```
https://www.synology.com/en-global/support/download/{MODEL}?version={VER}#packages
```

每行除版本套件按钮外，还会按该型号最高版本生成 4 个固定链接：
- DSM系统 → `#system`
- 安卓应用 → `#androids`
- 桌面程序 → `#utilities`
- 文档 → `#docs`

## 后台功能

- 列表：服务端分页（每页 10 条）、按型号/架构搜索
- 新增：填写架构 / 细分架构 / 型号，勾选可用 DSM 版本
- 编辑：点"编辑"按钮修改任意字段
- 删除：点"删除"按钮（带确认）
- 同一型号在不同架构下可以存在多行（原站即如此）

## 版本清单

后台勾选项包含完整 DSM 版本（已按群晖 API 实际返回校准）：

```
1.0 / 1.1 / 1.2 / 1.3 / 4.0 / 4.1 / 4.2 / 4.3 /
5.0 / 5.1 / 5.2 / 6.0 / 6.1 / 6.2 /
7.0 / 7.1 / 7.2 / 7.3 / 7.4
```

（6.3 实际不存在，已移除；1.x 为 RT 路由器固件）

## 更新记录

### v4.0 - 2026-10-23

**数据层**
- 新增 `bay_count` 盘位字段（INT，默认 0），旧库自动 ALTER TABLE 兼容
- 从群晖下载中心页面内嵌 JSON 提取 151 个在售型号的盘位映射，老型号按命名规则推断补全
- 调用群晖 `api/support/findDownloadInfo` API 逐个查询 216 个唯一型号的真实支持版本，174 个型号已用 API 精确版本覆盖（不再凭猜测批量补）
- 老 EOL 型号（CS406/DS101/DS106/DS207 等）保留原数据
- 版本清单校准为实际存在的 19 个版本，新增 4.0/4.1/4.2/7.3/7.4，移除 6.3

**前台 index.php**
- 表头新增"盘位"列（型号右侧），显示 `4-bay` / `2-bay`，无盘位显示 `—`
- 工具栏新增"架构"下拉 + "盘位"下拉，自动从数据库 DISTINCT 生成
- 下拉选择即时提交，可与关键词搜索叠加组合筛选
- 分页链接自动保留所有筛选条件

**后台 admin.php**
- 列表新增"盘位"列
- 新增/编辑表单新增"盘位"数字输入框（0-60）
- 列表同步加架构/盘位下拉筛选，URL 状态保持

### v3.0 - 2026-10-22

**前台**
- 改为服务端分页，每页 10 条，不再一次性加载全部 222 条
- 新增服务端搜索（按型号/架构过滤）
- 表格第一列增加 ID 号
- 每行版本按钮下方增加 4 个固定链接：DSM系统 / 安卓应用 / 桌面程序 / 文档
- 按钮宽度收紧为 10%，一行可排 9 个
- 版本清单扩展为 4.3 / 5.0 / 5.1 / 5.2 / 6.0 / 6.1 / 6.2 / 6.3 / 7.0 / 7.1 / 7.2
- 批量为老型号补全 4.3 / 5.0 / 5.1 / 5.2 老版本链接

**后台**
- 重写为服务端分页（每页 10 条），去掉 DataTables 依赖
- 增加按型号/架构搜索
- 列表第一列增加 ID 号

**主题**
- 机械姬背景图 3 张轮播，每 6 秒淡入淡出切换
- 按钮改为全透明描边，hover 时 90% 实色高亮
- 面板改为全透明玻璃质感，去除毛玻璃模糊
- 前台 / 后台 / 登录页三端风格统一

**基础设施**
- jQuery / DataTables / 背景图全部本地化，不依赖外网 CDN
- 数据库表结构按规范定稿（id / arch / sub_arch / model / versions / 时间戳 / 唯一约束）

### v1.0 - 初始版本

- 从静态 `synology.html` 解析 224 条型号数据
- 建立 SQLite 数据库 + 前台动态表格 + 后台 CRUD
