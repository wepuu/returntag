# ReturnTag 一期产品需求文档（PRD）

**文档版本：** V1.0  
**文档状态：** 开发基线  
**目标市场：** 美国（US）  
**代码仓库：** `returntag`  
**WordPress 插件：** `tagcore`  
**技术栈：** WordPress + WooCommerce + PHP + MySQL + Codex 自定义开发  
**商业模式：** 硬件一次性买断，核心找回服务免订阅费  
**产品家族：** `sticker`、`classic_tag`、`smart_tag`

---

## 1. 文档目的

本文档定义 ReturnTag 一期产品的业务范围、核心流程、数据边界、后台能力、安全要求和验收标准，作为产品、设计、开发、测试、运营和发布的统一基线。

本文档中的冻结需求未经正式 PRD 更新和架构决策记录（ADR）批准，不得由开发人员或 Codex 自行调整。

---

## 2. 最终命名规范

### 2.1 项目命名

| 对象 | 最终名称 |
|---|---|
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

ReturnTag 是一套面向美国消费者的物品防丢和找回平台，覆盖以下三种实体产品：

```text
sticker
classic_tag
smart_tag
```

每个实体标签均印刷或镭雕：

- 一个唯一的 6 位 Tag ID；
- 一个指向 ReturnTag 官方 HTTPS 域名的 QR 码。

用户通过扫描二维码或手动输入 6 位 Tag ID 激活标签。物品丢失后，发现者可以使用普通手机扫码，通过 ReturnTag 的双向隐私邮件中转联系失主。

Smart Tag 另行支持 Apple Find My 或其他兼容智能寻找网络，但智能定位网络与 ReturnTag QR 找回系统是两个相互独立、平行运行的系统。

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

- 无需下载 ReturnTag App；
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
- ReturnTag 为完成转发，需要受控处理双方邮箱；
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
10. Smart Tag 的智能定位网络与 ReturnTag QR 系统相互独立。
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
6. Finder 扫码并验证邮箱后，私密联系 Owner；
7. Owner 与 Finder 通过 ReturnTag 安全页面完成双向中转沟通；
8. Smart Tag 页面提供静态智能网络配置说明；
9. WooCommerce 订单与具体 Tag ID 保持完全解耦；
10. 后台支持批次冻结、标签暂停、所有权转移、争议处理和审计查询。

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
- Finder 图片或附件；
- 实时聊天；
- 浏览器精确定位。

---

## 7. 产品家族与能力矩阵

| 产品类型 | 对外名称 | QR + 6 位 ID | ReturnTag 隐私中转 | 智能寻找网络 |
|---|---|---:|---:|---:|
| `sticker` | ReturnTag Sticker | 是 | 是 | 否 |
| `classic_tag` | ReturnTag Classic Tag | 是 | 是 | 否 |
| `smart_tag` | ReturnTag Smart Tag | 是 | 是 | 是，独立运行 |

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

#### ReturnTag QR 找回系统

由 TagCore 负责：

- 标签激活；
- Owner 绑定；
- Lost Mode；
- Finder 扫码；
- 双向隐私邮件中转。

两个系统互不依赖：

```text
未激活 ReturnTag QR
≠
智能定位不可用
```

```text
未完成智能网络配对
≠
ReturnTag QR 不可用
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

> Your Smart Tag uses two separate recovery systems. Location tracking is managed in Apple Find My or the compatible finding app. ReturnTag does not access your Apple, Google, or location data. Activate QR recovery below so anyone with a phone can privately contact you.

页面可以提供：

- View Apple Find My setup guide；
- View compatible app setup guide；
- I’ve completed smart setup；
- Activate ReturnTag QR recovery。

系统最多可以保存：

```text
owner_pairing_ack_at
```

该字段仅表示用户主动确认已阅读或完成配置，不代表 ReturnTag 验证了真实配对状态。

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

二维码内容仅允许包含 ReturnTag 官方 HTTPS URL：

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
| 并发认领 | 数据库原子条件更新 |
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

### 14.5 已被其他人激活

当用户尝试激活已被其他账户认领的 ID 时：

- 不显示当前 Owner 信息；
- 不允许覆盖认领；
- 显示所有权争议入口；
- 提示准备购买证明和实体标签照片；
- 仅管理员审核后可以执行转移。

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

> Your Smart Tag’s location tracking is managed separately in Apple Find My or the compatible finding app. ReturnTag does not access your location data. Complete the QR recovery activation below as an additional way for anyone to contact you.

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

---

## 17. Finder 扫码体验

### 17.1 目标流程

Finder 的核心任务应在约 30 秒内完成：

```text
扫描 QR
→ 查看 Found Item 页面
→ 输入邮箱和留言
→ 接收验证邮件
→ 点击确认
→ ReturnTag 通知 Owner
→ Owner 安全回复
→ Finder 接收中转邮件
```

Finder 不需要注册完整账户。

### 17.2 Finder 表单

字段：

| 字段 | 要求 |
|---|---|
| Finder Email | 必填 |
| Message | 必填，10–500 字符 |
| Safety Confirmation | 确认不会索要密码或验证码 |
| Privacy Consent | 同意邮箱仅用于本次找回会话 |

一期禁止：

- 上传图片；
- 上传附件；
- 发送音频；
- 输入 HTML；
- 直接发送精确定位；
- 创建公开评论。

### 17.3 Finder 邮箱验证

Finder 提交表单后，系统不能立即通知 Owner。

正确流程：

```text
Finder 提交邮箱和消息
→ 创建 pending_verification 会话
→ 向 Finder 发送一次性确认链接
→ Finder 点击并确认
→ 会话变为 open
→ 系统向 Owner 发送通知
```

该步骤用于降低：

- 伪造他人邮箱；
- 垃圾消息；
- 恶意骚扰；
- 无效回信地址。

---

## 18. 双向隐私邮件中转

### 18.1 隐私要求

必须保证：

- Owner 看不到 Finder 的真实邮箱；
- Finder 看不到 Owner 的真实邮箱；
- 邮件正文不包含对方邮箱；
- 邮件头不使用对方邮箱作为 Reply-To；
- 双方通过 ReturnTag 安全页面回复；
- 普通客服后台默认只显示掩码邮箱。

掩码示例：

```text
j***@example.com
```

### 18.2 通信流程

```text
Finder 验证邮箱
→ ReturnTag 通知 Owner
→ Owner 点击 Secure Reply
→ Owner 输入回复
→ ReturnTag 转发给 Finder
→ Finder 点击 Continue Conversation
→ 双方继续有限次数的中转会话
```

### 18.3 Owner 通知邮件

推荐 Subject：

```text
A finder sent a message about your ReturnTag
```

推荐正文：

```text
A finder has contacted you through ReturnTag.

Message:
“I found this item near Terminal 4.”

Reply securely without sharing your email address:
[Secure Reply]
```

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
/account/conversations
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
tag_activation_conflict
tag_transferred
tag_suspended
tag_retired
finder_message_submitted
finder_email_verified
owner_reply_sent
conversation_closed
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

- Finder 必须验证邮箱；
- 消息限制 10–500 字符；
- HTML 全部转义；
- 禁止脚本；
- 禁止附件；
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
returntag_email_dispatch_enabled
returntag_woocommerce_account_enabled
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
| Woo Hook 异常 | 关闭 Woo Account Provisioning |

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
| 激活 | 激活冲突率 |
| Finder | 扫码到留言提交率 |
| Finder | Finder 邮箱验证率 |
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
20. 已激活标签不能被其他用户直接覆盖。
21. Tag Type 必须由数据库决定，不能由用户选择。
22. Smart Tag 激活不依赖 Apple 或 Google 配对。
23. Sticker 和 Classic Tag 不显示智能网络配置内容。

### 27.3 Smart Tag

24. 页面明确说明智能网络和 QR 找回是两个平行系统。
25. ReturnTag 不请求 Apple 或 Google 登录。
26. ReturnTag 不保存或显示位置数据。
27. ReturnTag 不声称已验证配对状态。
28. 即使智能网络不可用，QR Finder 流程仍正常工作。
29. QR 尚未激活时，不得声称智能网络功能失效。

### 27.4 Finder 与隐私中转

30. Finder 无需注册完整账户即可提交消息。
31. Finder 在邮箱验证前，Owner 不收到消息。
32. Owner 看不到 Finder 真实邮箱。
33. Finder 看不到 Owner 真实邮箱。
34. 邮件头、邮件正文和安全页面不得泄露对方邮箱。
35. Access Token 只能以哈希形式存储。
36. 邮件安全扫描器预访问链接时不得自动消费 Token。
37. 双方可以关闭或举报会话。
38. 会话可以过期。
39. Suspended 和 Retired 标签不能创建新会话。
40. 安全页面不加载广告追踪或第三方会话录制脚本。

### 27.5 后台与运营

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
RT-308 激活冲突处理
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
Finder 表单
Finder 邮箱验证
Conversation 创建
Owner 通知
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
→ 输入邮箱和消息
→ 验证 Finder 邮箱
→ ReturnTag 通知 Owner
→ 双方通过安全页面中转回复
→ Owner 确认找回并关闭会话
```

### 30.4 Smart Tag

```text
Apple / Google 智能寻找系统独立运行
+
ReturnTag QR 找回系统独立运行
```

---

## 31. 结论

ReturnTag 一期采用以下最终产品模型：

- 以 Batch 作为制造和 ID 生产单元；
- 以实体表面的 6 位 Tag ID 作为公开识别码和首次激活凭证；
- 不设置独立 Claim ID；
- 不建立订单、物流与 Tag ID 的映射；
- 以邮箱 OTP 完成无密码注册和登录；
- 以原子更新完成首次所有权认领；
- 以双向隐私邮件中转完成 Finder 与 Owner 的联系；
- 以独立平行系统方式支持 Smart Tag 智能网络；
- 以 Git、CI、Migration、Feature Flag 和版本化发布支持持续迭代与回滚。

由于公开 ID 同时承担激活凭证，Batch 激活开关、限流、邮箱验证、原子认领、导出审计和所有权争议流程均属于一期强制能力，而不是后续优化项。
