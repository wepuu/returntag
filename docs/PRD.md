# ForgeTag 一期产品需求文档（ReturnTag 项目 PRD）

**文档版本：** V1.1（RT-315 Finder Report 合同修订）
**文档状态：** 开发基线  
**目标市场：** 美国（US）  
**代码仓库：** `returntag`  
**WordPress 插件：** `tagcore`  
**技术栈：** WordPress + WooCommerce + PHP + MySQL + Codex 自定义开发  
**商业模式：** 硬件一次性买断，核心找回服务免订阅费  
**产品家族：** `sticker`、`classic_tag`、`smart_tag`

---

## 1. 文档目的

本文档定义 ForgeTag 一期消费者产品的业务范围、核心流程、数据边界、后台能力、安全要求和验收标准，作为产品、设计、开发、测试、运营和发布的统一基线。ReturnTag 是仓库、内部项目、代码命名空间、持久化前缀和既有技术合同使用的开发名称，不是消费者品牌。

本文档中的冻结需求未经正式 PRD 更新和架构决策记录（ADR）批准，不得由开发人员或 Codex 自行调整。

---

## 2. 最终命名规范

### 2.1 项目命名

| 对象 | 最终名称 |
|---|---|
| 消费者品牌 | `ForgeTag` |
| 内部项目名称 | `ReturnTag` |
| Git 仓库 | `returntag` |
| WordPress 插件显示名称 | `TagCore` |
| 插件目录 | `tagcore` |
| 插件入口文件 | `tagcore.php` |
| 插件 Slug | `tagcore` |
| WordPress Text Domain | `tagcore` |
| Composer 包名 | `returntag/tagcore` |
| PHP Namespace | `ReturnTag\\TagCore` |
| 数据库业务前缀 | `returntag_` |
| WordPress Option 前缀 | `returntag_` |
| Action / Filter Hook 前缀 | `returntag_` |
| PHP 全局函数前缀 | `returntag_` |
| 构建 ZIP | `tagcore-v{version}.zip` |
| Git Issue 编号前缀 | `RT-` |
| WordPress 主题显示名称 | `ForgeTag` |
| WordPress 主题目录与 Slug | `forge-tag` |
| WordPress 主题 Text Domain | `forge-tag` |

消费者可见的网站导航、页面标题、CTA、帮助内容、产品名称、Logo
替代文本和交易文案必须使用 `ForgeTag`。既有的 `ReturnTag` 技术标识不做
迁移或别名化；主题不得引入废弃的 `forgetag` 名称。

### 2.2 数据库命名

所有表名必须通过 `$wpdb->prefix` 动态生成，禁止硬编码 `wp_`。

示例：

```php
$tags_table = $wpdb->prefix . 'returntag_tags';
```

默认 WordPress 前缀为 `wp_` 时，实际表名为：

```text
wp_returntag_batches
wp_returntag_tags
wp_returntag_batch_exports
wp_returntag_auth_challenges
wp_returntag_conversations
wp_returntag_messages
wp_returntag_access_tokens
wp_returntag_events
```

---

## 3. 产品概述

ForgeTag 是一套面向美国消费者的物品防丢和找回平台，覆盖以下三种实体产品：

```text
sticker
classic_tag
smart_tag
```

每个实体标签均印刷或镭雕：

- 一个唯一的 6 位 Tag ID；
- 一个指向 ForgeTag 官方 HTTPS 域名的 QR 码。

用户通过扫描二维码或手动输入 6 位 Tag ID 激活标签。物品丢失后，发现者可以使用普通手机扫码，通过 ForgeTag 的双向隐私邮件中转联系失主。

Smart Tag 另行支持 Apple Find My 或其他兼容智能寻找网络，但智能定位网络与 ForgeTag QR 找回系统是两个相互独立、平行运行的系统。

### 3.1 网站与 Tag 入口

ForgeTag 使用同一个 WordPress 网站承载：

- 品牌官网与帮助内容；
- Tag 激活；
- Finder Report；
- Owner Dashboard；
- WooCommerce Shop、购物车和结账。

品牌网站提供 `Activate` 和 `Report` 两个明确入口。桌面端用户先点击入口，
再由 TagCore 打开弹窗并要求输入 6 位 Tag ID；品牌页面在用户点击前不直接
显示 Tag ID 输入框。移动端点击入口后进入 TagCore 全屏输入页面，不使用
弹窗。

扫描实体 QR 时，二维码已经提供 Tag ID，浏览器直接进入
`/t/{tag_id}`，不得要求用户再次输入 ID。

`Activate` 和 `Report` 只表达用户意图，不决定实际业务路径。TagCore
标准化并查询 ID 后，必须按服务端 Tag、Batch、功能开关和当前用户状态
进入激活、Owner、Finder、无效或状态说明页面。

---

## 4. 产品定位与核心价值

### 4.1 对标签所有者

- 核心找回服务无需支付月费或年费；
- 一个账户统一管理 Sticker、Classic Tag 和 Smart Tag；
- 无需公开手机号、邮箱或家庭地址；
- 任何使用智能手机的发现者均可扫码联系；
- Smart Tag 同时具备智能定位和 QR 扫码两条找回路径；
- 智能网络不可用、发现者不使用苹果设备或未安装对应 App 时，QR 扫码仍可使用。

### 4.2 对发现者

- 无需下载 ForgeTag App；
- 无需注册完整账户；
- 使用普通手机相机即可扫码；
- 不会看到失主的真实邮箱；
- 可以通过安全网页与失主完成有限次数的隐私沟通。

### 4.3 隐私价值定义

营销层可以使用：

```text
Double-Blind Privacy
```

产品、技术和隐私政策中应使用更准确的定义：

```text
Two-Way Private Email Relay
```

其含义为：

- Owner 看不到 Finder 的真实邮箱；
- Finder 看不到 Owner 的真实邮箱；
- ForgeTag 为完成转发，需要受控处理双方邮箱；
- 不宣称平台完全不知道双方身份；
- 不宣称端到端加密，除非未来确实完成相应技术实现。

---

## 5. 一期冻结业务规则

以下规则属于一期不可变业务基线：

1. 产品类型只能是：

   ```text
   sticker
   classic_tag
   smart_tag
   ```

2. 每个实体标签只有一个公开的 6 位 Tag ID。
3. 同一个 6 位 Tag ID 同时用于：
   - QR 路由；
   - 手动输入；
   - 用户首次激活；
   - Finder 扫码；
   - 客服查询。
4. 不设置独立 Claim ID、隐藏激活码或包装认领码。
5. WooCommerce 订单不绑定具体 Tag ID。
6. Amazon 订单不绑定具体 Tag ID。
7. 物流单、快递单、包裹和追踪号不绑定具体 Tag ID。
8. Tag ID 由后台按照批次、产品类型和指定数量批量生成。
9. 已生成、已导出、已作废或已退役的 Tag ID 永远不得重新使用。
10. Smart Tag 的智能定位网络与 ForgeTag QR 系统相互独立。
11. 一期不读取或保存 Apple、Google 的账户、设备、配对、位置和电量数据。
12. Smart Tag 已完成 MFi 等相关硬件认证，一期软件不承担认证流程。
13. 用户通过邮箱 OTP 完成注册或登录。
14. Finder 与 Owner 不得看到对方真实邮箱。
15. 核心找回功能不收取月费或年费。

---

## 6. 一期目标与非目标

### 6.1 一期目标

一期必须完成以下业务闭环：

1. 管理员按批次、产品类型和数量生成唯一 6 位 Tag ID；
2. 将 ID 和 QR URL 导出给生产厂家；
3. 用户通过实体标签上的二维码或 6 位 ID 激活；
4. 激活前通过邮箱 OTP 验证身份；
5. 用户在账户中心管理标签、公开信息和 Lost Mode；
6. Finder 扫码后提交一张必填的物品凭证照片和选填的 Owner 留言，无需注册或验证邮箱；
7. 凭证通过处理与安全检查后通知当前 Owner；Finder 只有在后续选填并验证邮箱后才能进入双向隐私中转；
8. Owner 与已验证邮箱的 Finder 通过 ForgeTag 安全页面完成双向中转沟通；
9. Smart Tag 页面提供静态智能网络配置说明；
10. WooCommerce 订单与具体 Tag ID 保持完全解耦；
11. 后台支持批次冻结、标签暂停、所有权转移、争议处理和审计查询。

### 6.2 一期明确不做

一期不包括：

- Apple Find My API 接入；
- Google 智能寻找网络 API 接入；
- Apple ID 登录；
- Google OAuth 登录；
- 智能标签位置地图；
- 位置历史；
- 自动读取配对状态；
- 自动读取电量状态；
- 蓝牙设备管理；
- WooCommerce Order 与 Tag ID 映射；
- Amazon Order 与 Tag ID 映射；
- Shipment、Tracking Number 与 Tag ID 映射；
- 独立 Claim ID；
- 包装隐藏认领码；
- 原生 iOS App；
- 原生 Android App；
- 短信验证码；
- Finder Report 规定的一张物品凭证照片之外的图片或附件；
- 实时聊天；
- 浏览器精确定位。

---

## 7. 产品家族与能力矩阵

| 产品类型 | 对外名称 | QR + 6 位 ID | ForgeTag 隐私中转 | 智能寻找网络 |
|---|---|---:|---:|---:|
| `sticker` | ForgeTag Sticker | 是 | 是 | 否 |
| `classic_tag` | ForgeTag Classic Tag | 是 | 是 | 否 |
| `smart_tag` | ForgeTag Smart Tag | 是 | 是 | 是，独立运行 |

### 7.1 Sticker

适用场景：

- AirPods；
- 水杯；
- 笔记本电脑；
- 相机；
- 平板；
- 小型电子设备；
- 高频、多件物品。

要求：

- 防水乙烯基材料；
- 每张 Sticker 都有独立 Tag ID；
- 每张 Sticker 独立激活；
- 可以组合包装销售，但一期不建立 Pack 认领关系。

### 7.2 Classic Tag

适用场景：

- 托运行李；
- 背包；
- 工具箱；
- 摄影器材；
- 宠物项圈；
- 长期高摩擦资产。

要求：

- 铝合金、皮革或其他耐磨材料；
- QR 和 ID 固定在产品表面；
- 配合钢丝环或其他耐用连接结构。

### 7.3 Smart Tag

Smart Tag 同时具备两套独立能力。

#### 智能寻找网络

由 Apple Find My 或其他兼容网络负责：

- 蓝牙连接；
- 设备配对；
- 定位；
- 播放声音；
- 智能网络账户管理。

#### ForgeTag QR 找回系统

由 TagCore 负责：

- 标签激活；
- Owner 绑定；
- Lost Mode；
- Finder 扫码；
- 双向隐私邮件中转。

两个系统互不依赖：

```text
未激活 ForgeTag QR
≠
智能定位不可用
```

```text
未完成智能网络配对
≠
ForgeTag QR 不可用
```

---

## 8. Smart Tag 平行系统边界

### 8.1 一期不保存的数据

TagCore 不得保存：

- Apple ID；
- Google Account；
- Apple Find My Device ID；
- Google Device ID；
- Apple 或 Google Access Token；
- 智能网络中的设备名称；
- 经纬度；
- 位置历史；
- 实际配对状态；
- 智能网络电量状态。

### 8.2 Smart Tag 页面文案要求

推荐英文说明：

> Your Smart Tag uses two separate recovery systems. Location tracking is managed in Apple Find My or the compatible finding app. ForgeTag does not access your Apple, Google, or location data. Activate QR recovery below so anyone with a phone can privately contact you.

页面可以提供：

- View Apple Find My setup guide；
- View compatible app setup guide；
- I’ve completed smart setup；
- Activate ForgeTag QR recovery。

系统最多可以保存：

```text
owner_pairing_ack_at
```

该字段仅表示用户主动确认已阅读或完成配置，不代表 ForgeTag 验证了真实配对状态。

页面不得显示：

```text
Connected to Apple
Apple pairing verified
Current location
Last seen location
Battery reported by Apple
Google account connected
```

---

## 9. 六位 Tag ID 规范

### 9.1 字符集

推荐字符集：

```text
23456789ABCDEFGHJKLMNPQRSTUVWXYZ
```

排除容易混淆的字符：

```text
0
1
I
O
```

示例：

```text
A7R2W9
K4M8PX
```

### 9.2 输入标准化

用户输入后系统必须：

1. 去除首尾空格；
2. 删除内部空格；
3. 删除连字符；
4. 转换为大写；
5. 验证长度必须为 6；
6. 验证所有字符属于允许字符集。

示例：

```text
a7-r2 w9
```

标准化为：

```text
A7R2W9
```

### 9.3 生成要求

必须使用密码学安全随机源，例如 PHP `random_int()`。

禁止使用：

```text
rand()
mt_rand()
时间戳截取
自增 ID 转码
批次号加流水号
其他可预测序列
```

数据库必须在 `tag_id` 上设置唯一索引。发生碰撞时，应用服务自动重新生成。

ID 一旦写入数据库：

- 不允许修改；
- 不允许重新分配新 ID；
- 不允许物理删除后复用；
- 不允许重新生成覆盖。

### 9.4 QR URL

二维码内容仅允许包含 ForgeTag 官方 HTTPS URL：

```text
https://returntag.com/t/A7R2W9
```

二维码中不得包含：

- Owner 邮箱；
- Owner ID；
- 用户姓名；
- 订单号；
- 位置；
- Claim Secret；
- 任何个人信息。

---

## 10. 公开 ID 同时用于激活的风险控制

由于 6 位 ID 公开印在实体标签表面，同时也是首次激活凭证，任何在真实买家之前看到该 ID 的人都有可能尝试认领。

这是当前产品方案的已知残余风险，无法通过邮箱 OTP 完全消除，必须采用以下补偿控制：

| 风险 | 控制措施 |
|---|---|
| 厂家人员提前认领 | 新 Batch 默认关闭激活 |
| 导出文件泄露 | 导出审计、校验值、受限访问、批次冻结 |
| 自动遍历 ID | 随机 ID、IP 限流、设备限流、风险 CAPTCHA |
| 恶意批量认领 | 邮箱、IP、设备的小时和日级限额 |
| 并发认领 | 数据库原子条件更新，未成功请求重新解析已提交状态 |
| 伪造邮箱 | 激活前必须完成邮箱 OTP |
| ID 已被抢占 | 所有权争议处理流程 |
| 搜索引擎收录 | `noindex`、`nofollow`、`noarchive` |
| 某批次数据泄露 | Batch 级暂停和作废能力 |

该决策在以下 ADR 中长期记录：

```text
docs/adr/0007-public-tag-id-is-activation-id.md
```

---

## 11. 批次生成与生产导出

### 11.1 Batch 定义

一个 Batch 表示一次 ID 生成和生产任务。

创建 Batch 时，管理员填写：

| 字段 | 说明 |
|---|---|
| Batch Code | 企业内部唯一批次编号 |
| Tag Type | `sticker`、`classic_tag` 或 `smart_tag` |
| Model Code | 具体硬件型号，可为空 |
| Smart Network | Smart Tag 使用的智能网络类型 |
| Quantity | 本次需要生成的 ID 数量 |
| Manufacturer | 生产厂家，可选 |
| Sales Channel | `direct`、`amazon`、`mixed` 或其他 |
| Notes | 批次备注 |
| Activation Enabled | 默认关闭 |

`smart_network` 建议值：

```text
none
apple_find_my
google_find_hub
other
```

该字段仅用于型号说明和页面渲染，不代表系统接入对应网络。

### 11.2 Batch 状态

```text
draft
generating
generated
exported
released
suspended
voided
```

| 状态 | 含义 |
|---|---|
| `draft` | 草稿，尚未生成 ID |
| `generating` | 正在后台生成 |
| `generated` | ID 已生成，但尚未导出 |
| `exported` | 已导出给厂家 |
| `released` | 允许最终用户激活 |
| `suspended` | 暂停该批次未激活标签的新激活 |
| `voided` | 批次作废，未激活 ID 永久不可用 |

暂停一个 Batch 默认只阻止该批次中尚未激活的标签，不自动停用已经被正常用户激活的标签。

### 11.3 生成流程

```text
管理员创建 Batch
→ 输入产品类型、型号和数量
→ 系统显示二次确认
→ 管理员确认
→ 后台队列分批生成 ID
→ 每个 ID 写入 Tags 表
→ 持续更新生成进度
→ 校验生成数量
→ Batch 进入 generated
→ 管理员导出 CSV
→ Batch 进入 exported
→ 产品准备上市后开启激活
→ Batch 进入 released
```

大批量生成不得在单次浏览器请求中同步完成。

后台必须显示：

- 目标数量；
- 已生成数量；
- 失败数量；
- 当前进度；
- 开始时间；
- 完成时间。

### 11.4 生成后不可修改

Batch 完成生成后：

- 不允许修改 Tag Type；
- 不允许修改已生成 Tag ID；
- 不允许减少数量；
- 如需增加数量，应创建新 Batch；
- 如发现错误，应暂停或作废原 Batch，并创建新 Batch；
- 不得通过重新生成覆盖已交付给厂家的 ID。

### 11.5 CSV 导出

推荐字段：

```csv
sequence_no,batch_code,tag_id,tag_type,model_code,smart_network,qr_url
1,ST-2026-001,A7R2W9,sticker,,,https://returntag.com/t/A7R2W9
2,ST-2026-001,K4M8PX,sticker,,,https://returntag.com/t/K4M8PX
```

不得导出：

```text
Owner ID
用户邮箱
WooCommerce Order ID
Amazon Order ID
Claim ID
OTP
Token
消息正文
```

每次导出必须记录：

- Batch ID；
- 导出版本；
- 导出行数；
- 文件格式；
- SHA-256 校验值；
- 操作管理员；
- 导出时间。

重新导出时必须得到同一组 ID，不得重新生成。

---

## 12. 订单与 Tag ID 完全解耦

系统不保存以下映射：

```text
WooCommerce Order → Tag ID
WooCommerce Order Item → Tag ID
Amazon Order → Tag ID
Shipment → Tag ID
Tracking Number → Tag ID
```

WooCommerce 负责：

- 商品；
- SKU；
- 数量；
- 支付；
- 订单；
- 发货；
- 账单邮箱。

TagCore 负责：

- Batch；
- Tag ID；
- Owner；
- 激活；
- Lost Mode；
- Finder 会话；
- 隐私邮件中转。

该设计意味着：

- 退款不会自动释放标签；
- 退货不会自动改变 Owner；
- 系统无法精确知道某个订单包含哪些 ID；
- 订单不能作为自动认领标签的依据；
- 激活率只能进行批次级或渠道级近似分析。

---

## 13. 用户注册、登录与激活

### 13.1 WooCommerce 渠道

订单变为 Completed 后：

```text
读取账单邮箱
→ 检查 WordPress 用户是否存在
→ 不存在则创建账户
→ 已存在则不修改密码
→ 发送“收到标签后扫码激活”的引导邮件
```

该流程不得：

- 生成 Tag ID；
- 分配 Tag ID；
- 认领 Tag；
- 向订单写入 Tag ID；
- 向 Tag 表写入 Order ID。

WooCommerce 账户只是用户入口，不是标签所有权凭证。

礼品场景下，最终收到实体标签的人可以使用自己的邮箱完成激活。实际 Owner 以完成标签激活的账户为准。

### 13.2 Amazon 和其他外部渠道

```text
收到实体标签
→ 扫描 QR 或输入 6 位 ID
→ 输入邮箱
→ 接收 6 位 OTP
→ 验证 OTP
→ 登录已有账户或创建新账户
→ 确认激活
→ 填写物品信息
→ Tag 进入 Active
```

### 13.3 WooCommerce 用户未登录扫码

即使该邮箱已经存在 WordPress 账户，用户在未登录状态下扫码时仍使用邮箱 OTP 登录。

系统不得要求用户记住 WordPress 密码。

### 13.4 OTP 规则

| 项目 | 一期要求 |
|---|---|
| 格式 | 6 位数字 |
| 有效期 | 10 分钟 |
| 最大输入次数 | 5 次 |
| 重发间隔 | 60 秒 |
| 单邮箱限制 | 分钟、小时和日级限制 |
| 单 IP 限制 | 分钟和小时级限制 |
| 存储方式 | 只保存哈希，不保存明文 |
| 使用后 | 立即标记为已消费 |
| 过期后 | 不可使用 |

OTP 不得只保存于 WordPress Transient，必须使用独立认证挑战表。

---

## 14. 标签状态模型

### 14.1 标签生命周期状态

`tag_status` 允许值：

```text
unregistered
active
suspended
retired
```

| 状态 | 含义 |
|---|---|
| `unregistered` | 尚未被用户激活 |
| `active` | 已绑定 Owner，可正常使用 |
| `suspended` | 因争议、安全或滥用被暂停 |
| `retired` | 永久停用，不能重新激活 |

### 14.2 Lost Mode

Lost Mode 单独保存：

```text
lost_mode = 0
lost_mode = 1
```

不能将 `lost` 与 `active` 放入同一个互斥状态字段，因为一个 Active 标签可以同时处于 Lost Mode。

### 14.3 扫码路由

公开地址：

```text
GET /t/{tag_id}
```

处理顺序：

```text
标准化 ID
→ 查询 Tag
→ 查询所属 Batch
→ 检查全局和 Batch 开关
→ 检查 Tag 状态
→ 检查当前用户身份
→ 渲染对应页面
```

| 条件 | 页面 |
|---|---|
| ID 不存在 | 通用无效标签页 |
| Batch 未 Released 且 Tag 未激活 | 暂不可激活页 |
| Tag 为 Unregistered | Owner 激活入口 |
| Tag 为 Active 且当前用户是 Owner | 标签管理入口 |
| Tag 为 Active 且当前用户不是 Owner | Finder 联系页 |
| Tag 为 Suspended | 暂停服务页 |
| Tag 为 Retired | 标签已停用页 |

#### 14.3.1 站内手动入口与扫码入口

站内手动入口遵循：

```text
桌面品牌页面点击 Activate 或 Report
→ TagCore 弹窗输入 Tag ID
→ 标准化并解析服务端状态
→ 进入对应 TagCore 流程
```

```text
移动品牌页面点击 Activate 或 Report
→ TagCore 全屏页面输入 Tag ID
→ 标准化并解析服务端状态
→ 进入对应 TagCore 流程
```

扫码入口遵循：

```text
扫描 QR
→ 直接访问 /t/{tag_id}
→ 标准化并解析服务端状态
→ 进入对应 TagCore 流程
```

入口按钮不得强制指定状态。用户从 `Activate` 输入 Active ID 时，仍必须
按 Owner 或 Finder 规则处理；用户从 `Report` 输入 Unregistered ID 时，
仍必须按激活规则处理。

`/t/{tag_id}` 的路由注册、规范化、状态解析、当前用户判断、访问控制、
隐私响应头、限流和业务处理全部属于 TagCore。主题不得复制这些逻辑。
该路由必须在主题更换、主题集成不可用或 JavaScript 不可用时独立工作。

主题通过 TagCore 服务端渲染的动态区块 `tagcore/tag-entry-link` 放置入口。
该区块只接受闭集 `activate` 或 `report` 展示意图，由 TagCore 使用 WordPress
API 生成同站点 URL。主题不得硬编码域名、子目录或入口路径。TagCore 未来
提供的规范手动入口为：

```text
GET /tag/activate/
GET /tag/report/
```

两个 GET 入口只显示手动输入界面，不执行变更。提交 Tag ID 的方法、验证、
限流、CSRF 决策、安全错误和规范 `303` 跳转必须在实现工单中单独定义并
测试；本文档不表示这些入口或区块已经实现。

### 14.4 原子激活

认领必须使用数据库条件更新或事务，确保并发时只有一人成功。

示例：

```sql
UPDATE {tags_table}
SET
    owner_id = %d,
    tag_status = 'active',
    activated_at = UTC_TIMESTAMP(),
    updated_at = UTC_TIMESTAMP()
WHERE tag_id = %s
  AND owner_id IS NULL
  AND tag_status = 'unregistered';
```

只有受影响行数为 1，才表示激活成功。

激活前还必须确认：

- 全局激活开关开启；
- 所属 Batch 允许激活；
- Tag 未被暂停、作废或退役。

### 14.5 并发激活后的状态收敛

激活请求未取得原子条件更新权时，不生成第四种“激活冲突”产品状态，
也不在激活流程中显示所有权争议或客服入口。服务端必须重新解析已经
提交的 Tag、Batch 和当前用户状态，并进入既有三分流：

- 当前用户已经是 Owner：进入 Owner 标签页面；
- Tag 已由其他账户激活：进入 Finder 归还流程；
- Tag 不存在、已失效或无法提供服务：进入对应无效或状态说明页。

任何未成功请求都不得覆盖已提交的 Owner，不得显示 Owner 身份或邮箱，
也不得依据请求参数推断所有权。用户如需帮助，可自行使用网站现有客服
渠道；客服入口不属于激活状态机。

---

## 15. 不同产品类型的激活页面

### 15.1 Sticker

显示：

- Sticker 产品说明；
- 私有物品名称；
- 可选公开名称；
- Lost Mode 说明；
- 激活按钮。

### 15.2 Classic Tag

显示：

- Classic Tag 产品说明；
- 挂签使用建议；
- 私有物品名称；
- 可选公开名称；
- Lost Mode 设置。

### 15.3 Smart Tag

除普通激活字段外，额外显示：

> Your Smart Tag’s location tracking is managed separately in Apple Find My or the compatible finding app. ForgeTag does not access your location data. Complete the QR recovery activation below as an additional way for anyone to contact you.

Smart Tag 激活不得依赖 Apple 或 Google 配对，也不得调用 Apple 或 Google API。

---

## 16. Owner 账户中心

一期账户中心至少包括：

| 功能 | 说明 |
|---|---|
| My Tags | 查看全部已激活标签 |
| Tag Detail | 查看产品类型、状态和基本信息 |
| Rename | 修改私有物品名称 |
| Public Label | 设置 Finder 可见名称 |
| Lost Mode | 开启或关闭丢失模式 |
| Lost Message | 设置安全的公开找回说明 |
| Conversations | 查看 Finder 会话 |
| Secure Reply | 通过安全页面回复 Finder |
| Transfer | 将标签转移给其他邮箱 |
| Retire | 永久停用标签 |
| Test Email | 测试通知邮件 |
| Smart Setup | 查看 Smart Tag 静态说明 |
| Privacy | 导出或删除个人数据 |

### 16.1 私有名称与公开名称

建议字段拆分：

```text
item_name
public_label
```

示例：

```text
item_name: John's Work MacBook
public_label: Silver Laptop
```

`item_name` 仅 Owner 可见。

Finder 页面只允许显示：

- `public_label`；
- 产品类型；
- Owner 设置的 Lost Message。

### 16.2 Lost Mode

Active 标签即使未开启 Lost Mode，也允许 Finder 联系 Owner。

开启 Lost Mode 后可以额外显示：

- The owner has marked this item as lost；
- 自定义安全交接说明；
- 可选奖励说明；
- 更醒目的联系按钮。

Lost Message 不允许填写：

- 密码；
- 验证码；
- 银行账户；
- 身份证件号码；
- 完整家庭地址。

### 16.3 RT-317 Stage 0 Owner Dashboard contract

TagCore owns `/account/sign-in/`, `/account/`,
`/account/tags/{tag_id}/`, and `/account/conversations/`. The authenticated
WordPress user is the only Owner identity input. A Tag ID or Conversation
selector supplied by the browser selects a candidate only; every read,
mutation, and Secure Reply continuation must re-resolve current ownership on
the server. Unknown, unauthenticated, transferred, or unauthorized candidates
use a generic non-enumerating response.

Account sign-in uses passwordless email OTP under an Account-specific purpose
and rate-limit domain. It must not create Tag ownership, overwrite an existing
password, or expose whether an email or Owner exists. Successful verification
establishes the approved WordPress session; every later Owner action still
requires a fresh server-side ownership check.

My Tags and Tag Detail may show the current Owner the Tag ID, product type,
Tag status, `item_name`, `public_label`, Lost Mode state, and bounded
presentation timestamps. Suspended and retired Tags are read-only. A
transferred Tag is removed from the previous Owner projection immediately.
Conversation summaries show only status and bounded activity metadata; they
must not show either email address, message bodies, Tokens, evidence, media
references, or evidence filenames.

Stage 2 mutations are separate same-site, Nonce-protected POST actions and
require an `active` Tag:

- `item_name`: optional Owner-only plain text, maximum 191 Unicode characters;
- `public_label`: optional Finder-visible plain text, maximum 191 Unicode
  characters;
- `lost_mode`: canonical boolean independent from `tag_status`;
- `lost_message`: optional Finder-visible plain text, maximum 500 Unicode
  characters, with HTML and approved high-risk secrets or complete home
  addresses rejected;
- Smart Setup acknowledgement: one idempotent UTC write to
  `owner_pairing_ack_at`, never pairing proof and never location, device,
  battery, Apple, or Google account data.

Account Conversation entry does not render or authorize messages directly.
An explicit POST revalidates the current active Owner and the complete
Conversation eligibility graph, revokes prior Owner sessions for that
Conversation, and issues the existing role-bound 30-minute Secure Reply
session. GET cannot mint access, and the WordPress Account session alone
cannot read or send relay messages.

Account runtime has an independent, non-autoloaded, default-disabled incident
control:

```text
returntag_owner_account_enabled
```

The control is not authorization and must not be replaced with the activation
flag. Disabling it makes Account routes and mutations unavailable without
changing ownership, activation, public scan, Finder recovery, emailed Secure
Reply links, or existing Conversation state.

RT-317 implementation stages are read-only Account, bounded metadata/Lost
Mode mutations, and Conversation summaries/Secure Reply continuation.
Transfer, Retire, Test Email, privacy export/deletion, and administrative
moderation require separate runtime contracts.

---

## 17. Finder 扫码体验

### 17.1 目标流程

Finder 的核心任务应在约 30 秒内完成：

```text
扫描 QR
→ 查看 Found Item 页面
→ 选填给 Owner 的留言
→ 上传一张必填的物品凭证照片
→ ForgeTag 私密处理并检查照片
→ ForgeTag 通知当前 Owner
→ Finder 可选填并验证邮箱以开启安全回复
```

Finder 不需要注册完整账户，也不需要提供或验证邮箱即可完成首次单向上报。

### 17.2 Finder 表单

字段：

| 字段 | 要求 |
|---|---|
| Message for the owner | 选填，填写时为 10–500 字符 |
| Item photo | 必填，且只能上传一张物品凭证照片 |
| Finder Email | 选填；仅用于后续验证并开启双向回复 |
| Safety Confirmation | 确认不会索要密码或验证码 |
| Privacy Consent | 同意凭证照片会经处理后以内嵌缩略图发送给 Owner；提供邮箱时，同意邮箱仅用于本次找回流程 |

除一张必填的 Item photo 外，一期禁止：

- 上传更多图片或通用附件；
- 发送音频；
- 输入 HTML；
- 直接发送精确定位；
- 创建公开评论。

Item photo 是 Finder 证明已找到物品的安全凭证，不是 Owner 的物品资料，也不是 Conversation 附件。系统只接受 JPEG、PNG 或 WebP，源文件最大 8 MiB、解码像素不超过 20 MP；必须校验文件签名与实际 MIME，成功解码后重新编码，移除 EXIF、GPS、设备、时间和原始文件名元数据，并保存在非公开、加密的私有存储中。SVG、GIF、HEIC、PDF、视频、音频和无法安全解码的文件必须拒绝。

Owner 邮件只允许内嵌通过安全检查的派生缩略图，不得附带原图，也不得使用公开 URL、远程热链或可转发的访问 Token。邮件派生图最长边不超过 800 px、目标大小不超过 200 KiB。用于安全复核的派生主图最长边不超过 1600 px。Finder 必须被明确告知：系统可以按保留策略删除服务器副本，但无法撤回 Owner 邮箱已经接收、缓存或转发的副本。

### 17.3 单向 Finder Report

Finder Report 与双向 Conversation 是两个独立模型。报告提交后，系统先持久化报告与隔离的凭证，再由后台任务完成解码、重新编码、元数据清理和内容安全检查。只有凭证状态为可用时，系统才能解析发送时的当前 Owner 并排队通知；处理失败、被拒绝、超时或安全服务不可用时必须失败关闭，不向 Owner 发送不安全或空内容通知。

正确流程：

```text
Finder 提交选填留言和一张必填凭证照片
→ 创建独立 Finder Report 并隔离凭证
→ 后台处理与内容安全检查
→ 解析发送时的当前 Owner
→ 幂等排队并发送含内嵌派生缩略图的 Owner 通知
```

初次通知不创建 `pending_verification` Conversation，也不允许 Owner 回复匿名 Finder。系统必须对 Tag、直接对等 IP、设备/风险信号和全局提交量执行原子限流；风险升高时可以使用经批准的 CAPTCHA 适配器，但 CAPTCHA 不能替代服务端校验、凭证检查或限流。

### 17.4 可选邮箱验证与双向回复

Finder 可以在初次上报时或之后选填邮箱。系统必须先验证该邮箱，成功后才可创建或关联 canonical Conversation、向 Owner 提供 Secure Reply，并把回复投递给 Finder。验证前不得把匿名报告伪装成可回复会话，也不得将 Owner 回复保存为等待匿名 Finder 领取的消息。

---

## 18. 双向隐私邮件中转

### 18.1 隐私要求

必须保证：

- Owner 看不到 Finder 的真实邮箱；
- Finder 看不到 Owner 的真实邮箱；
- 邮件正文不包含对方邮箱；
- 邮件头不使用对方邮箱作为 Reply-To；
- 双方通过 ForgeTag 安全页面回复；
- 普通客服后台默认只显示掩码邮箱。

掩码示例：

```text
j***@example.com
```

### 18.2 通信流程

```text
Finder 可选填并验证邮箱
→ ForgeTag 将 Finder Report 关联到 canonical Conversation
→ Owner 点击 Secure Reply
→ Owner 输入回复
→ ForgeTag 转发给 Finder
→ Finder 点击 Continue Conversation
→ 双方继续有限次数的中转会话
```

### 18.3 Owner Finder Report 通知邮件

推荐 Subject：

```text
A finder submitted a report about your ForgeTag
```

推荐正文：

```text
A finder submitted evidence through ForgeTag.

Evidence photo:
[Processed inline thumbnail]

Message for you:
“I found this item near Terminal 4.”
```

如果 Finder 未填写 Message，邮件必须省略整个留言区。只有 Finder 邮箱已经验证并且关联 Conversation 已打开时，邮件或后续安全页面才能显示 Secure Reply。Subject、正文、内嵌图片文件名、CID、邮件头和链接不得包含私人 item name、任一方邮箱、Tag ID 或原始文件名。

不建议在 Subject 中显示具体物品名称，以减少锁屏通知泄露。

### 18.4 Token 安全

访问 Token 必须：

- 使用高强度随机源；
- 数据库只保存哈希；
- 设置有限有效期；
- 支持撤销；
- 不写入普通日志；
- 不在 GET 第一次访问时直接消费；
- 通过 POST 或 Continue 操作建立安全会话。

RT-315 Stage 6 固定以下运行时边界：

- Owner Secure Reply 与 Finder Continue Conversation 邮件链接有效期为 24 小时；
- 链接 Token 为 32 字节密码学随机值，数据库只保存独立密钥域的 SHA-256 HMAC；
- 显式 POST 成功交换后创建 30 分钟的 HttpOnly、SameSite=Strict 安全会话；
- 同一角色与 Conversation 的旧会话在新会话签发时撤销；
- Owner 每次交换和操作都必须重新验证当前 active Owner，所有权转移后旧路径立即失效。

### 18.5 消息范围与次数

- Owner 与 Finder 的每条消息均为必填纯文本，长度为 10–500 个 Unicode 字符；
- 每个角色最多发送 10 条人工消息，每个 Conversation 最多发送 20 条人工消息；
- `system` 投递记录不计入人工消息限额；
- 一期 Conversation 不支持附件、图片、音频、视频、HTML 或精确位置字段；
- 消息正文必须使用独立外部密钥加密存储，队列只允许携带 Message ID；
- 邮件提供者接受只记录为 `sent`，不得当作确认送达；过期的模糊投递认领必须失败关闭，不得自动重复发送。

### 18.6 Stage 7A 参与者安全操作

- Finder 只能通过其已验证、角色绑定的会话显式结束当前 `open`
  Conversation，并将其转换为 `closed`；
- 当前 active Owner 只能通过其角色绑定会话执行 `Report and block`，将
  当前 `open` Conversation 转换为 `blocked`；
- 两个操作均要求同站点、Nonce 保护的 POST、显式确认和服务端角色、当前
  Owner、Tag、Finder Report 及 Conversation 状态复核；
- 状态转换、全部未撤销 Token/Session 撤销、仍为 `queued` 的 Message
  终止及无元数据审计 Event 必须在同一事务中完成；
- Finder 结束记录 `conversation_closed`，Owner 举报并屏蔽记录
  `conversation_reported`，Event 不得包含原因、邮箱、消息正文、Tag ID、
  Token 或媒体标识；
- Stage 7A 不接收举报原因、自由文本、附件或位置，也不实现重新打开、解除
  屏蔽、审核结果、证据保留、申诉、所有权争议或后台审核界面；
- 已经进入外部邮件提供商调用的消息无法召回，但关闭或屏蔽不能被该调用
  恢复，且事务前签发的继续访问 Token 必须失效。

相关页面响应头：

```text
Cache-Control: no-store
Referrer-Policy: no-referrer
X-Robots-Tag: noindex, nofollow, noarchive
```

以下页面不得加载广告像素、第三方会话录制或不必要的追踪脚本：

```text
/t/{tag_id}
/activate
/finder-confirm
/secure-reply
/account/sign-in/
/account/
/account/tags/{tag_id}/
/account/conversations/
```

---

## 19. 数据模型

### 19.1 `returntag_batches`

职责：保存生产批次、生成数量和激活控制。

关键字段：

```text
batch_id
batch_code
tag_type
model_code
smart_network
manufacturer
sales_channel
requested_quantity
generated_quantity
batch_status
activation_enabled
notes
created_by
created_at
updated_at
```

### 19.2 `returntag_tags`

职责：保存每个实体标签、Owner、状态和物品信息。

关键字段：

```text
tag_id
batch_id
owner_id
tag_type
model_code
item_name
public_label
tag_status
lost_mode
lost_message
owner_pairing_ack_at
activated_at
owner_changed_at
last_scanned_at
created_at
updated_at
```

该表不得包含：

```text
claim_id
claim_secret
order_id
order_item_id
amazon_order_id
shipment_id
apple_device_id
google_device_id
latitude
longitude
```

### 19.3 `returntag_batch_exports`

职责：记录生产文件导出历史。

关键字段：

```text
export_id
batch_id
export_version
row_count
file_format
file_checksum
created_by
created_at
```

### 19.4 `returntag_auth_challenges`

职责：保存 OTP、Finder 邮箱验证和其他一次性认证挑战。

关键字段：

```text
challenge_id
purpose
subject_type
subject_id
email_ciphertext
email_lookup
code_hash
attempt_count
send_count
ip_hash
expires_at
verified_at
consumed_at
created_at
```

### 19.5 `returntag_conversations`

职责：保存 Finder 与 Owner 的会话。

关键字段：

```text
conversation_id
tag_id
owner_id_snapshot
finder_email_ciphertext
finder_email_lookup
finder_verified_at
conversation_status
expires_at
last_activity_at
created_at
```

会话状态：

```text
pending_verification
open
closed
blocked
expired
```

### 19.6 `returntag_messages`

职责：保存会话中的具体消息和投递状态。

关键字段：

```text
message_id
conversation_id
sender_role
body_ciphertext
delivery_status
provider_message_id
delivered_at
dispatch_claimed_at
dispatch_attempt_count
created_at
```

Sender Role：

```text
finder
owner
system
```

### 19.7 `returntag_access_tokens`

职责：保存安全回复链接、Magic Link 和会话访问 Token。

关键字段：

```text
token_id
conversation_id
purpose
actor_role
token_hash
expires_at
exchanged_at
revoked_at
created_at
```

### 19.8 `returntag_events`

职责：保存业务审计事件。

事件示例：

```text
batch_created
batch_generation_started
batch_generation_completed
batch_exported
batch_released
batch_suspended
tag_activation_started
otp_verified
tag_activated
tag_transferred
tag_suspended
tag_retired
finder_message_submitted
finder_report_submitted
finder_report_evidence_ready
finder_report_owner_notified
finder_report_blocked
finder_email_verified
owner_reply_sent
conversation_closed
conversation_reported
recovery_confirmed
ownership_dispute_opened
ownership_dispute_resolved
```

审计日志不得保存：

- 明文 OTP；
- 完整 Token；
- 完整消息正文；
- 不必要的完整邮箱；
- Apple 或 Google 位置数据。

### 19.9 `returntag_finder_reports`（Schema 9）

职责：保存无需邮箱验证的单向 Finder Report 状态。RT-315 阶段 1 通过 expand Migration `0009` 创建该表和类型化 Repository，但不注册公开写入路径。

计划字段至少包括：

```text
finder_report_id
tag_id
owner_id_at_submission
message_ciphertext (nullable)
report_status
evidence_status
owner_notification_status
owner_notified_at
expires_at
created_at
updated_at
```

报告状态必须使用独立词汇：

```text
received
processing
ready
notified
blocked
expired
```

这些值不是 Conversation 状态，不得添加到 `returntag_conversations.conversation_status`。发送时必须重新解析当前 Owner；`owner_id_at_submission` 仅用于审计和转移竞争检测，不是发送授权。

### 19.10 `returntag_finder_report_media`（Schema 10）

职责：保存一张 Finder Report 凭证的私有对象引用、处理状态、加密与完整性元数据、尺寸、派生版本和保留期限。不得保存公开媒体 URL、原始文件名、EXIF、GPS、设备信息或邮件访问 Token。媒体状态为：

```text
quarantined
processing
ready
rejected
deleted
```

原图和派生图必须位于 WordPress 公共 uploads/媒体库之外的加密私有存储。数据库只保存不具备公开访问能力的对象引用和必要的非敏感处理元数据。RT-315 阶段 1 通过 expand Migration `0010` 创建该表和类型化 Repository。阶段 2 增加未注册到公开流程的加密私有存储、图片校验/去元数据派生和失败关闭的内容安全接口；公开上传与持久化编排仍由后续阶段实现。

---

## 20. 管理后台需求

### 20.1 Batch Management

后台列表至少显示：

- Batch Code；
- Tag Type；
- Model Code；
- Smart Network；
- 目标数量；
- 已生成数量；
- 已激活数量；
- Batch Status；
- Activation Enabled；
- 导出次数；
- 创建时间；
- 最近导出时间。

支持操作：

- 创建 Batch；
- 开始生成；
- 查看生成进度；
- 导出 CSV；
- 重新导出相同数据；
- 开启激活；
- 暂停新激活；
- 作废未激活 ID；
- 查看 Batch 内的 Tags；
- 查看操作日志。

### 20.2 Tag Management

支持按以下条件查询：

- 6 位 Tag ID；
- Batch Code；
- Owner 邮箱；
- Owner User ID；
- Tag Type；
- Tag Status；
- Lost Mode；
- 激活时间。

Tag 详情页显示：

- Tag ID；
- Batch；
- 产品类型；
- 型号；
- 当前 Owner；
- 激活时间；
- Lost Mode；
- 会话数量；
- 最近扫描时间；
- 状态变化日志。

敏感操作必须二次确认并记录操作管理员：

- Suspend；
- Retire；
- Remove Owner；
- Transfer Owner；
- Close Conversations；
- Resolve Dispute。

### 20.3 所有权争议

由于订单不记录 Tag ID，客服处理争议时可以要求：

- 实体标签正反面照片；
- 带时间信息的标签照片；
- 购买凭证；
- 外包装照片；
- 产品型号；
- 当前持有情况；
- 争议原因。

处理结果：

```text
reject
transfer_to_new_owner
suspend_pending_review
retire_tag
```

---

## 21. WooCommerce 集成要求

WooCommerce Completed Hook 只负责：

- 查询或创建 WordPress 用户；
- 不修改已有用户密码；
- 发送“收到标签后扫码激活”的引导邮件；
- 记录来源事件。

不得负责：

- 生成 Tag ID；
- 分配 Tag ID；
- 认领 Tag；
- 修改 Batch；
- 将订单与 Tag 绑定。

必须兼容 WooCommerce HPOS，并通过 WooCommerce 公共 API 读取订单，不得直接查询订单存储表。

Hook 必须幂等：

- 重复触发不得重复创建用户；
- 重复触发不得重复发送大量邮件；
- 重复触发不得写入 Tag 或 Batch 数据。

---

## 22. 邮件与后台任务

### 22.1 事务邮件类型

一期包括：

- WooCommerce 激活引导；
- 邮箱 OTP；
- Finder 邮箱确认；
- Owner 找回通知；
- Owner 回复 Finder；
- Finder 回复 Owner；
- 标签转让；
- 所有权争议通知；
- 账户安全通知。

### 22.2 邮件队列

邮件不得在公共页面请求中同步发送。

推荐流程：

```text
用户提交
→ 数据库写入完成
→ 添加后台发送任务
→ 事务邮件服务发送
→ Webhook 更新投递状态
→ 失败自动重试
```

投递状态：

```text
queued
sent
delivered
deferred
bounced
complained
failed
```

不能将 `wp_mail()` 返回成功等同于邮件已经送达。

Finder Report 的 Owner 通知必须在报告和安全派生图提交后异步排队。队列参数只能包含内部 report ID；Worker 在发送前重新解析当前 Owner，验证 Finder evidence 开关、Finder contact 开关和 Email dispatch 开关，并以 report ID 与派生版本建立幂等键。重复任务不得重复通知，永久失败不得无限重试。内嵌图使用本地 MIME CID，不能是远程 URL 或原始附件。

---

## 23. 安全与反滥用

### 23.1 激活安全

- Batch 未 Released 时禁止激活；
- 单个 Tag 只允许首次原子认领；
- 每个邮箱限制短期内激活数量；
- 每个 IP 限制激活尝试次数；
- 风险升高时启动 CAPTCHA；
- 不向匿名用户批量返回 ID 是否存在；
- 所有输入进行标准化、验证和长度限制；
- 激活成功后发送安全通知邮件。

### 23.2 Finder 消息安全

- 初次 Finder Report 无需邮箱验证；Finder 邮箱在开启双向 Conversation 前必须验证；
- Message for the owner 选填，填写时限制 10–500 字符；
- Item photo 必填且恰好一张，并执行签名/MIME 校验、像素和大小限制、解码重编码、元数据清理、内容安全检查和私有加密存储；
- HTML 全部转义；
- 禁止脚本；
- 禁止 Item photo 之外的通用附件；
- 对 URL 做风险提示或限制；
- 单 Tag 限制短时间会话数量；
- Owner 可屏蔽和举报；
- Finder 可终止会话；
- 被举报会话进入后台审核。

### 23.3 敏感数据安全

- OTP 仅保存哈希；
- Access Token 仅保存哈希；
- Finder 邮箱加密存储；
- Finder 邮箱可额外保存 HMAC Lookup 以支持限流和去重；
- 消息正文建议加密存储；
- 加密密钥不得存入同一数据库；
- 日志不得写入完整邮箱、OTP、Token 或消息正文；
- 管理员权限必须最小化。

---

## 24. Feature Flags 与紧急开关

一期必须实现：

```text
returntag_global_activation_enabled
returntag_finder_contact_enabled
returntag_finder_evidence_enabled
returntag_email_dispatch_enabled
returntag_woocommerce_account_enabled
returntag_owner_account_enabled
```

Batch 层另有：

```text
activation_enabled
```

故障处理：

| 故障 | 处理 |
|---|---|
| 大量恶意认领 | 关闭 Global Activation |
| 某批次 ID 泄露 | 关闭该 Batch Activation |
| 邮件出现隐私问题 | 关闭 Email Dispatch |
| Finder 垃圾消息暴增 | 关闭 Finder Contact |
| Finder 凭证处理、内容安全或媒体隐私异常 | 关闭 Finder Evidence；未通过处理的报告不得通知 Owner |
| Woo Hook 异常 | 关闭 Woo Account Provisioning |
| Owner Account 权限、隐私或写入异常 | 关闭 Owner Account；不得改变已有所有权、Finder 或 Secure Reply 状态 |

Feature Flag 用于快速止损，不替代代码修复和正式发布回滚。

---

## 25. 数据分析与 KPI

### 25.1 北极星指标

```text
Confirmed Recoveries per 1,000 Activated Tags
```

即每 1,000 枚已激活标签产生的确认找回数量。

### 25.2 核心指标

| 类型 | 指标 |
|---|---|
| 生产 | Batch ID 生成成功率 |
| 生产 | 导出数量与生成数量一致率 |
| 激活 | Tag 激活成功率 |
| 激活 | OTP 请求到验证完成率 |
| 激活 | 并发激活状态收敛成功率 |
| Finder | 扫码到 Finder Report 提交率 |
| Finder | 凭证处理通过率与拦截率 |
| Finder | 选择双向回复的 Finder 邮箱验证率 |
| 通知 | Owner 邮件投递率 |
| 会话 | Owner 回复率 |
| 会话 | 首次回复时间 |
| 找回 | Confirmed Recovery 数量 |
| 安全 | 垃圾会话率 |
| 安全 | 所有权争议率 |
| 质量 | Tag Suspend 和 Retire 比例 |

### 25.3 订单解耦后的数据限制

以下指标无法进行精确的单订单计算：

- 每个 WooCommerce 订单的标签激活率；
- 每个 Amazon 订单的标签激活率；
- 某个退款订单对应标签的使用情况；
- 某一具体买家的未激活标签数量。

可使用以下近似方式：

- Batch 记录渠道；
- 比较渠道销量与同期激活数量；
- 记录用户账户来源；
- 使用批次级和月度 Cohort。

---

## 26. 一期 MVP 范围

### 26.1 P0：必须上线

```text
三种 Tag Type
Batch 创建
六位 Tag ID 批量生成
CSV 导出
导出版本和 SHA-256
Batch 激活开关
QR 和手动 ID 路由
邮箱 OTP 登录
标签原子激活
Owner Dashboard
Lost Mode
Finder 邮箱验证
Finder Report 必填凭证处理与单向 Owner 通知
双向隐私会话
邮件队列和投递状态
标签转让
所有权争议后台
Smart Tag 静态配置说明
审计日志
限流和滥用控制
Feature Flags
```

### 26.2 P1：上线后优化

```text
QR SVG / PNG 文件包导出
多个 ID 连续快速激活
Sticker 多枚激活模式
西班牙语 Finder 页面
Trusted Contact
家庭共享
批次级渠道报表
更完整的客服工单系统
二维码印刷质量检测
企业资产批量管理
```

---

## 27. 核心验收标准

### 27.1 Batch 与生产

1. 管理员可以创建 `sticker`、`classic_tag` 和 `smart_tag` Batch。
2. 系统可以按指定数量生成全局唯一的 6 位 ID。
3. 生成器只使用允许字符集。
4. CSV 行数必须等于 Batch 生成数量。
5. 同一 ID 不得存在于两个 Batch。
6. 重新导出不得重新生成 ID。
7. Batch 未 Released 时，未注册标签不能激活。
8. 系统中不得生成 Claim ID。
9. Tag 表和 Batch 表不得保存 WooCommerce 或 Amazon Order ID。
10. 已导出、已作废和已退役 ID 永远不得复用。
11. Batch 生成中断后可以安全恢复。
12. Batch 暂停默认不影响已激活 Owner 的正常使用。

### 27.2 激活

13. 用户可以扫码或手动输入 ID。
14. 匿名用户必须通过邮箱 OTP。
15. 已有账户通过 OTP 后登录，不创建重复用户。
16. OTP 不以明文保存。
17. 过期、已消费或超过尝试次数的 OTP 不可使用。
18. 首位成功认领者成为 Owner。
19. 两人并发认领同一标签时只有一人成功。
20. 已激活标签不能被其他用户直接覆盖；未成功的并发激活必须重新解析状态，并收敛到 Owner、Finder 或无效/状态说明页，不显示激活冲突页。
21. Tag Type 必须由数据库决定，不能由用户选择。
22. Smart Tag 激活不依赖 Apple 或 Google 配对。
23. Sticker 和 Classic Tag 不显示智能网络配置内容。

### 27.3 Smart Tag

24. 页面明确说明智能网络和 QR 找回是两个平行系统。
25. ForgeTag 不请求 Apple 或 Google 登录。
26. ForgeTag 不保存或显示位置数据。
27. ForgeTag 不声称已验证配对状态。
28. 即使智能网络不可用，QR Finder 流程仍正常工作。
29. QR 尚未激活时，不得声称智能网络功能失效。

### 27.4 Owner Account

- Account 只显示当前 WordPress 用户拥有的 Tags，并在每次读取和写入时
  重新验证所有权；
- `item_name` 只允许当前 Owner 查看，Finder 页面不得接收该字段；
- Suspended 和 Retired Tags 在 Account 中只读，转移后的旧 Owner 路径必须
  泛化失败；
- Account GET 不得执行写入或签发 Secure Reply 会话；
- WordPress Account 登录不能直接授权 Conversation 消息读取或发送；
- `returntag_owner_account_enabled` 缺失、无效或关闭时 Account 失败关闭，
  但不影响现有所有权、扫码、激活、Finder 或邮件 Secure Reply；
- Account 页面满足键盘、标签、可见焦点、移动端和 200% 缩放要求，且不
  加载广告、会话录制或不必要的第三方追踪。

### 27.5 Finder 与隐私中转

30. Finder 无需注册完整账户、提供邮箱或验证邮箱即可提交初次单向 Finder Report。
31. Message for the owner 为选填且填写时限制为 10–500 字符；Item photo 为必填且恰好一张。
32. 只有通过处理和内容安全检查的派生图才可通知 Owner。
33. 初次 Owner 通知不得创建或暗示可回复的 Conversation；Finder 邮箱验证成功前，Owner 不能向 Finder 发送回复。
34. Finder 选填并验证邮箱后，系统才可创建或关联 canonical Conversation 并开启双向中转。
35. Owner 看不到 Finder 真实邮箱。
36. Finder 看不到 Owner 真实邮箱。
37. 邮件头、邮件正文和安全页面不得泄露对方邮箱、私人 item name、Tag ID 或凭证原始文件名。
38. Access Token 只能以哈希形式存储；邮件安全扫描器预访问链接时不得自动消费 Token。
39. 双方可以关闭或举报已验证会话；Owner 可以举报或屏蔽 Finder Report；报告和会话均可以按各自保留策略过期。
40. Suspended 和 Retired 标签不能创建新报告或会话；安全页面不加载广告追踪或第三方会话录制脚本。

### 27.6 后台与运营

41. 管理员可以按 ID、Batch、Owner 和状态查询。
42. 所有权转移必须写入审计日志。
43. Batch 导出必须记录数量、版本和校验值。
44. 管理员不能通过修改 WooCommerce 订单自动改变 Tag Owner。
45. 管理员可以暂停泄露批次的新标签激活。
46. 已激活标签是否暂停，必须通过独立 Tag 状态控制。
47. 所有敏感后台操作必须记录操作人和时间。

### 27.6 WooCommerce

48. Completed Hook 不生成或分配 Tag ID。
49. Completed Hook 不向订单写 Tag ID。
50. Completed Hook 不向 Tag 表写 Order ID。
51. 重复触发 Hook 不重复创建用户。
52. 重复触发 Hook 不重复发送大量邮件。
53. 必须兼容 WooCommerce HPOS。

### 27.7 前端交付边界

54. 桌面品牌页面必须先由用户点击 `Activate` 或 `Report`，再显示 Tag ID 输入弹窗。
55. 移动端从品牌页面点击 `Activate` 或 `Report` 后，必须使用全屏 TagCore 输入页面。
56. 扫描 QR 进入 `/t/{tag_id}` 后不得再次要求输入 Tag ID。
57. `Activate` 或 `Report` 入口意图不得覆盖服务端解析的 Tag 状态。
58. `/t/{tag_id}` 必须在主题更换或 JavaScript 不可用时独立工作。
59. 主题不得注册 Tag 路由、查询 TagCore 表、判断 Owner 或执行产品业务操作。
60. 桌面弹窗和移动全屏流程必须复用 TagCore 的验证、访问控制和业务服务。
61. 敏感 TagCore 页面不得通过 iframe 嵌入，也不得加载不必要的第三方追踪。
62. 低保真线框图仅作为设计参考，不构成最终视觉、布局或文案验收基准。
63. 后续页面效果图必须经过产品、响应式、隐私、安全和可访问性复核后才能成为实施目标。
64. ForgeTag 主题目录必须为 `theme/forge-tag/`，Text Domain 必须为 `forge-tag`。
65. 主题必须通过 `tagcore/tag-entry-link` 动态区块放置 Activate 和 Report 入口，不得硬编码入口域名或路径。
66. `tagcore/tag-entry-link` 只能接受 `activate` 或 `report` 展示意图，不得接受 Tag、Owner、邮箱、Token、权限或状态值。
67. `/tag/activate/` 和 `/tag/report/` 必须由 TagCore 注册并提供无 JavaScript 的服务端回退。
68. WooCommerce 不可用时，品牌内容和 TagCore 入口仍必须可用；WooCommerce 不是激活依赖。
69. 生产 Site Editor 变更必须导出、评审、测试并通过 Git 和不可变 Theme 产物发布。
70. Theme V1 必须提供 Shop Archive、Single Product、Cart 和 Checkout 的基础 Block Theme 模板，但仅作为设计系统和响应式基线，不得承载电商业务规则或被视为缺少效果图页面的最终视觉定稿。

---

## 28. 开发与发布约束

### 28.1 插件工程边界

所有核心业务逻辑必须位于独立插件：

```text
plugin/tagcore/
```

不得将核心业务逻辑放入：

- 主题 `functions.php`；
- Elementor 自定义代码；
- 页面模板；
- WooCommerce 邮件模板；
- 单个超大 PHP 文件。

ForgeTag WordPress 主题可以负责：

- 品牌官网、导航、页脚、帮助和编辑内容；
- WooCommerce 商品、购物车和结账的表现层；
- 放置 TagCore 提供的 Activate、Report、Account 等集成组件；
- 提供经批准的品牌设计 Token。

Theme V1 包含 Shop Archive、Single Product、Cart 和 Checkout 的基础 Block
Theme 模板，只建立设计系统与响应式表现基线。WooCommerce 未启用时品牌
内容仍须可用，且 WooCommerce 不得成为 Tag 激活、Finder、QR 路由或账户
授权的依赖。

主题不得：

- 注册或接管 `/t/{tag_id}`；
- 查询 TagCore 数据表或判断 Tag、Batch、Owner 状态；
- 实现激活、Finder、Secure Reply 或 Owner Dashboard 业务操作；
- 将浏览器提交的 Owner ID 当作权限依据；
- 复制 TagCore 的桌面弹窗或移动全屏业务表单。

桌面弹窗、移动全屏手动入口和扫码直达页面必须复用同一个 TagCore
状态模型。桌面弹窗是渐进增强，不得成为完成流程的唯一方式，也不得使用
iframe 嵌入敏感的 `/t/{tag_id}` 页面。

一期公开与账户页面继续使用 PHP 服务端渲染、语义化 HTML、可选的
WordPress Interactivity API 渐进增强以及插件作用域 CSS。不引入
Next.js、Tailwind 或全局 CSS Reset。

### 28.2 代码分层

```text
Domain
Application
Infrastructure
Admin
PublicSite
Account
WooCommerce
```

Domain 层不得直接依赖：

```text
$wpdb
wp_mail()
get_option()
update_option()
WC_Order
```

### 28.3 Git 管理

- 仓库名：`returntag`；
- `main` 始终保持可部署；
- 禁止直接 Push `main`；
- 一个 Issue 对应一个分支和一个 Pull Request；
- 每个 PR 必须通过 CI；
- 生产发布通过 Git Tag 构建不可变 ZIP；
- 已合并错误通过 Revert PR 修复，不允许 Force Push `main`。

### 28.4 版本与回滚

- 数据库采用向前兼容 Migration；
- 不依赖破坏性的生产 `down()` Migration；
- 已生成 Tag ID、导出记录、Owner 认领和审计事件不得因代码回滚而删除；
- 生产故障优先关闭 Feature Flag，再部署上一稳定版本；
- 数据库结构必须保证上一稳定代码在合理窗口内仍可读取。

---

## 29. 开发里程碑

### Milestone 0：工程基础（v0.1.0）

```text
RT-001 仓库和文档结构
RT-002 TagCore 插件骨架
RT-003 Composer 自动加载
RT-004 PHPCS、PHPStan、PHPUnit
RT-005 GitHub Actions CI
RT-006 ZIP 构建脚本
RT-007 Feature Flag
RT-008 基础日志接口
```

### Milestone 1：数据库与 Migration（v0.2.0）

```text
RT-101 Migration Runner
RT-102 Batches 表
RT-103 Tags 表
RT-104 Batch Exports 表
RT-105 Auth Challenges 表
RT-106 Conversations 和 Messages 表
RT-107 Access Tokens 表
RT-108 Events 表
RT-109 Repository 接口
RT-110 安装和升级测试
```

### Milestone 2：Batch 与 ID 生产（v0.3.0）

```text
RT-201 Batch 创建后台
RT-202 六位安全 ID 生成器
RT-203 碰撞重试
RT-204 后台分批生成
RT-205 生成进度
RT-206 Batch Tag ID 清单与确定性导出基础
RT-207 审计 CSV 导出（版本、行数、格式、操作员、时间和 SHA-256）
RT-208 Batch Release / Suspend
RT-209 Tag 搜索
RT-210 大批量压力测试
```

### Milestone 3：扫码、OTP 与激活（v0.4.0）

```text
RT-301 /t/{tag_id} 路由
RT-302 ID 标准化
RT-303 Tag 状态页面
RT-304 OTP 请求
RT-305 OTP 验证
RT-306 Passwordless 登录注册
RT-307 原子激活
RT-308 并发激活状态收敛
RT-309 激活限流
RT-310 Smart Tag 静态说明
```

### Milestone 4：Owner Dashboard（v0.5.0）

```text
My Tags
Tag Detail
Private Name
Public Label
Lost Mode
Lost Message
Smart Setup
Test Email
Transfer / Retire
Owner 权限测试
```

### Milestone 5：Finder 隐私中转（v0.6.0）

```text
Finder Report 表单（Message 选填、Item photo 必填）
私有凭证存储、处理、内容安全与保留
无需邮箱验证的单向 Owner 通知
Finder 可选邮箱验证
验证后 Conversation 创建或关联
Secure Reply Token
Owner 回复
Finder 继续会话
会话关闭和过期
举报与屏蔽
邮件投递状态
```

### Milestone 6：WooCommerce（v0.7.0）

```text
Completed Hook
账户创建或查找
不修改已有密码
激活引导邮件
Hook 幂等
WooCommerce HPOS 兼容
Gift 场景
订单与 Tag 解耦测试
```

### Milestone 7：运营与争议（v0.8.0）

```text
标签转让
所有权争议
管理员转移
Tag Suspend
Batch Suspend
会话关闭
审计日志
数据保留任务
后台权限分级
敏感操作二次确认
```

### Milestone 8：发布准备（v0.9.0 → v1.0.0）

```text
并发激活测试
批量 ID 压力测试
邮件投递测试
移动端测试
无障碍测试
浏览器兼容测试
WordPress 升级测试
WooCommerce 升级测试
数据库 Migration 测试
插件代码回滚测试
备份恢复演练
安全审计
```

---

## 30. 最终系统流程

### 30.1 厂家生产

```text
后台创建 Batch
→ 生成指定数量的唯一 6 位 ID
→ 导出 ID 和 QR URL
→ 厂家将 QR 和 ID 制作到产品表面
→ 管理员在产品上市前开启 Batch 激活
```

### 30.2 用户激活

```text
用户从任意渠道收到产品
→ 扫描实体 QR 或输入 6 位 ID
→ 邮箱 OTP
→ 首次认领
→ 设置物品信息
→ 标签进入 Active
```

### 30.3 Finder 找回

```text
Finder 扫描实体 QR
→ 选填 Message for the owner
→ 上传一张必填 Item photo
→ ForgeTag 处理并安全检查凭证
→ ForgeTag 通知发送时的当前 Owner
→ Finder 可选填并验证邮箱
→ 验证后双方可通过安全页面中转回复
→ Owner 确认找回并关闭会话
```

### 30.4 Smart Tag

```text
Apple / Google 智能寻找系统独立运行
+
ForgeTag QR 找回系统独立运行
```

---

## 31. 结论

ForgeTag 一期采用以下最终产品模型：

- 以 Batch 作为制造和 ID 生产单元；
- 以实体表面的 6 位 Tag ID 作为公开识别码和首次激活凭证；
- 不设置独立 Claim ID；
- 不建立订单、物流与 Tag ID 的映射；
- 以邮箱 OTP 完成无密码注册和登录；
- 以原子更新完成首次所有权认领；
- 以无需邮箱验证的单向 Finder Report 快速通知 Owner，并在 Finder 可选验证邮箱后通过双向隐私邮件中转继续联系；
- 以独立平行系统方式支持 Smart Tag 智能网络；
- 以 Git、CI、Migration、Feature Flag 和版本化发布支持持续迭代与回滚。

由于公开 ID 同时承担激活凭证，Batch 激活开关、限流、原子认领、导出审计和所有权争议流程均属于一期强制能力，而不是后续优化项。Owner 身份仍通过邮箱 OTP 验证；Finder 初次单向报告不需要邮箱验证，但任何双向回复都必须先完成 Finder 邮箱验证。Finder 凭证的私有存储、处理、内容安全、幂等通知和专用紧急开关同样属于上线前强制能力。
