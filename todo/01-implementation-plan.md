# 实施方案

## 一、仓库结构

建议初始结构：

```text
bookstack-content-sync/
├── src/
├── tests/
├── todo/
│   ├── logs/
│   ├── 00-项目总览.md
│   ├── 01-实施方案.md
│   ├── 02-进度追踪.md
│   └── logs/README.md
├── composer.json
└── README.md
```

说明：

- `src/`：扩展包源码
- `tests/`：包级测试
- `todo/`：中文方案、进度、执行日志
- `todo/logs/`：按日期或轮次记录执行日志

## 二、技术路线

### Phase 1：方案与骨架

目标：确定包边界与接入方式，不急于迁移实现。

事项：

- 明确包名、命名空间、目录结构
- 设计 service provider 方案
- 设计 Composer 安装与本地 path repository 联调方式
- 建立进度和日志机制

产出：

- 方案文档
- 初始仓库结构
- `composer.json` 草案

### Phase 2：代码迁移

目标：从当前 BookStack 仓库迁移 sync 相关实现到扩展包。

候选迁移内容：

- `ContentSync/Pull/*`
- `ContentSync/Push/*`
- `ContentSync/Shared/*`
- 对应 console commands

注意：

- 新包必须使用独立命名空间
- 不直接复用 `BookStack\ContentSync\...` 命名空间
- 尽量保持逻辑不变，先搬运再整理

### Phase 3：宿主接入

目标：让扩展包安装进 BookStack 后可直接工作。

事项：

- 注册 service provider
- 注册 Artisan commands
- 配置必要容器绑定
- 保留对 BookStack 内部服务的依赖

### Phase 4：验证与发布准备

目标：证明外部安装路径可用。

事项：

- 在 BookStack 宿主项目中使用 path repository 安装
- 验证 pull / push / execute 三条命令
- 整理 README 安装文档
- 整理兼容性说明

## 三、关键设计决策

### 1. 包定位

定位为“运行在 BookStack 内部的外部 Composer 扩展包”，不是独立 CLI，也不是通用 Laravel 插件。

### 2. 命令兼容策略

优先保留现有命令名，减少迁移成本：

- `bookstack:pull-content`
- `bookstack:push-content`

如后续遇到冲突，再评估新增命名空间前缀命令。

### 3. 兼容策略

短期采用“文档声明支持的 BookStack 版本范围”，不先做复杂适配层。

### 4. 迁移策略

优先做“复制迁移 + namespace 调整”，避免一开始做大量重构。

## 四、待确认问题

1. Composer 包名最终采用什么命名
2. provider 使用 auto-discovery 还是文档要求手动注册
3. 是否需要保留旧仓库中的实验实现作为开发来源
4. 最低支持的 BookStack / Laravel / PHP 版本范围
