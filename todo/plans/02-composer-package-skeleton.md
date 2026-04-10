# 方案：Composer 包最小骨架

## 背景

包名与命名空间方案已经进入待确认状态，下一步需要定义这个扩展包的最小可运行骨架。

这里的目标不是立即迁移全部代码，而是先明确：

- `composer.json` 需要包含哪些最小字段
- `src/` 目录应该先放哪些骨架类
- provider 如何注册
- 哪些内容现在先占位，哪些内容可以延后

## 目标

- 给出一个可落地的最小 Composer 包结构
- 明确第一批要创建的文件
- 明确哪些部分先保留为空骨架
- 为下一步实际创建骨架文件提供确认依据

## 方案选项

### 方案 A

先创建“最小可注册骨架”：

- `composer.json`
- `src/Providers/ContentSyncServiceProvider.php`
- `src/Console/Commands/PullContentCommand.php`
- `src/Console/Commands/PushContentCommand.php`
- `src/ContentSync/` 空目录或占位说明
- `tests/` 空目录

特点：

- 先打通包结构与自动加载
- 命令类可以先保留空实现或最小提示
- 后续再迁移真实逻辑

优点：

- 风险最低
- 可以最早验证 Composer 安装与 provider 注册
- 便于分阶段提交

缺点：

- 初期命令还不能真正工作
- 需要后续再进行一次迁移补全

### 方案 B

创建“骨架 + 第一批真实迁移”：

- `composer.json`
- provider
- commands
- `src/ContentSync/Shared/*`
- `src/ContentSync/Pull/*`
- `src/ContentSync/Push/*` 的基础结构

特点：

- 一开始就把主要目录结构与核心类迁过去
- 骨架阶段就接近成型

优点：

- 后续返工少
- 目录形态一次到位

缺点：

- 首轮变更较大
- 还没验证安装骨架就开始搬代码，风险更高

### 方案 C

先只写 `composer.json` 和 provider，不创建命令骨架。

优点：

- 最小改动
- 文档和元信息可先确定

缺点：

- 太薄，验证价值有限
- 下一步还得很快补命令和目录结构

## 推荐方案

推荐采用方案 A。

推荐理由：

- 当前仍处于方案确认期，适合先把“包能被识别和加载”作为第一目标
- 命令类和 provider 是最关键的接入点
- 先搭空骨架，再迁真实代码，更容易控制节奏
- 对日志、进度和回滚都更友好

## 建议创建的第一批文件

- `composer.json`
- `src/Providers/ContentSyncServiceProvider.php`
- `src/Console/Commands/PullContentCommand.php`
- `src/Console/Commands/PushContentCommand.php`
- `src/ContentSync/.gitkeep`
- `tests/.gitkeep`

## composer.json 建议字段

建议至少包括：

- `name`
- `description`
- `type`
- `license`
- `require`
- `autoload.psr-4`
- `extra.laravel.providers`

其中：

- `name` 暂按上一份方案推荐值
- `type` 建议为 `library`
- provider 先使用 Laravel package discovery

## Provider 建议职责

第一阶段只做两件事：

- 注册命令
- 预留后续容器绑定位置

暂不做：

- 复杂配置发布
- 多环境判断
- 兼容层适配

## 待确认点

1. 是否接受“先空骨架、后迁代码”的节奏
2. provider 是否先采用 auto-discovery
3. 是否允许命令骨架先返回提示文本，而不是立即接入真实逻辑
4. 是否要在第一轮就创建 `tests/` 目录

## 结论

- 状态：待确认
- 当前推荐：方案 A
