# 方案：最小 Pull 实跑验证

## 背景

当前已经完成以下验证：

- 本地 path repository 安装成功
- Laravel package discovery 成功
- `bookstack:pull-content` 命令在宿主中可见
- 命令归属已确认由外部包实现接管

下一步需要验证的，是 pull 链路是否真的能从命令进入 runner，并进一步触达配置读取、远端 API 调用与本地文件输出。

## 目标

- 构造一个最小 sync 项目目录
- 在宿主项目中执行一次真实 `bookstack:pull-content`
- 观察失败点或成功结果
- 识别当前外部包还缺哪些依赖、配置或兼容处理

## 推荐验证方式

### 输入准备

创建一个临时目录，例如：

- `/tmp/bookstack-content-sync-smoke`

并写入最小 `sync.json`，字段包括：

- `version`
- `app_url`
- `content_path`
- `env_vars.token_id`
- `env_vars.token_secret`

其中：

- `app_url` 使用当前本地 BookStack 地址
- token 环境变量沿用现有本地测试凭据，或临时设置

### 执行步骤

1. 创建空目录
2. 写入 `sync.json`
3. 设置所需环境变量
4. 在宿主项目中执行：
   - `php artisan bookstack:pull-content /tmp/bookstack-content-sync-smoke`

### 结果判定

#### 成功

如果成功，应至少看到：

- pull 阶段输出
- `snapshot.json`
- `content/` 目录

#### 失败

如果失败，记录失败层级：

- 命令层
- 容器绑定层
- 配置加载层
- API 调用层
- 文件写入层

## 风险点

### 1. 运行环境变量未设置

如果 token 变量不存在，命令会在 credential 解析阶段失败。

### 2. 本地 BookStack API 地址与认证状态不匹配

如果 `app_url`、token 或本地服务状态不对，可能在 API 请求阶段失败。

### 3. 扩展包依赖声明不完整

虽然宿主安装成功，但运行时仍可能暴露 package 侧缺少显式 `require` 的问题。

## 推荐策略

推荐直接做一次真实最小 pull。

原因：

- 安装和命令接管都已经验证完毕
- 当前最需要暴露的是运行时问题，而不是继续停留在静态检查
- 即使失败，也能给下一步补依赖或修集成提供精确信号

## 待确认点

1. 是否直接使用 `/tmp/bookstack-content-sync-smoke` 作为测试目录
2. 是否沿用当前本地 BookStack 的可用 API token
3. 如果 pull 成功，是否保留测试目录用于后续 push 验证
4. 如果 pull 失败，是否立即进入修复而不是先迁移 push

## 结论

- 状态：待确认
- 当前推荐：直接执行一次最小 pull 实跑验证
