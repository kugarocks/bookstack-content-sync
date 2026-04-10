# 方案：Push Execute 验证

## 背景

当前扩展包已经完成以下验证：

- pull 在宿主中真实运行成功
- push 命令已由扩展包接管
- push plan 在宿主中真实运行成功
- 当前测试目录 `/tmp/bookstack-content-sync-smoke` 的 plan 结果为 `No remote changes required`

因此，剩余尚未实证的链路主要是：

- `bookstack:push-content {projectPath} --execute`
- 远端写入执行器链路
- 本地 metadata / snapshot 回写链路

## 目标

- 评估是否适合在当前数据状态下做一次受控 execute 验证
- 明确 execute 验证的风险边界
- 给出推荐执行或暂缓执行的判断标准

## 当前条件评估

### 有利条件

- 命令接管已经确认
- push plan 已成功运行
- 当前测试目录来源于刚刚成功的 pull 结果
- 当前 plan 输出为 `No remote changes required`

### 风险条件

- execute 具有真实远端写入能力
- 如果当前目录被额外修改，execute 可能不再是无副作用
- 即使 plan 显示无变化，也需要确认 execute 路径在“无变化”场景下是否会触发本地回写逻辑

## 可选策略

### 方案 A：在当前目录上做一次无变更 execute 验证

执行对象：

- `/tmp/bookstack-content-sync-smoke`

前提：

- 先重新运行一次 push plan
- 再确认仍然是 `No remote changes required`
- 然后执行：
  - `php artisan bookstack:push-content /tmp/bookstack-content-sync-smoke --execute`

优点：

- 风险最低
- 可验证 execute 命令路径是否在无变化场景下正常结束
- 不需要先制造变更样本

缺点：

- 不能证明真实远端写入动作可用
- 只能证明 execute 命令本身可进入并正确处理“无变化”分支

### 方案 B：制造一个受控本地变更，再执行 execute

示例：

- 修改一个 page 内容或标题
- 先跑 push plan 确认只有预期变更
- 再执行 `--execute`

优点：

- 能验证真正的远端写入与本地回写链路

缺点：

- 风险更高
- 会影响当前本地 BookStack 数据
- 一旦失败，清理成本更高

### 方案 C：当前阶段不做 execute，只先补依赖与文档

优点：

- 零运行风险
- 适合先把包定义和文档补完整

缺点：

- execute 链路风险继续保留

## 推荐方案

推荐采用方案 A。

推荐理由：

- 当前 push plan 已成功且目录状态稳定
- 在“无变化”场景做 execute，风险明显低于直接制造写操作
- 可以先验证 execute 命令入口、runner 分支、输出和退出状态
- 如果这一步顺利，再决定是否做带真实写入的下一轮验证

## 受控执行前检查项

1. 重新运行一次 `bookstack:push-content {projectPath}`
2. 确认输出仍然是 `No remote changes required`
3. 确认测试目录只包含 pull 导出结果，没有额外人为修改
4. 明确当前轮 execute 目标仅为“无变化 execute 验证”

## 成功标准

### 本轮成功

- `bookstack:push-content {projectPath} --execute` 在无变化场景下成功结束
- 没有异常报错
- 没有出现意外远端写入迹象

### 后续成功

- 在受控变更样本上验证真实远端写入与本地回写

## 待确认点

1. 是否接受当前轮只做“无变化 execute 验证”
2. 是否暂不做真实写入型 execute 验证
3. execute 验证后是否优先补文档而不是继续扩展测试矩阵

## 结论

- 状态：待确认
- 当前推荐：先做无变化场景下的 execute 验证
