# ForgeTag V1 设计资产 Manifest

- **状态：** RT-313 规范性资产基线
- **版本：** 1.0
- **基线日期：** 2026-08-01
- **目录：** `docs/design/`
- **后续工单：** RT-314 ForgeTag FSE Theme V1

本 Manifest 将 ForgeTag V1 设计资料收敛为可审计、可版本控制的源素材基线。它记录文件身份、生产准入和禁止用途，不会创建 WordPress 主题或改变 TagCore 运行时行为。

## 1. 分类规则

| 分类 | 效力 | 是否允许主题运行时使用 |
|---|---|---:|
| `production-approved` | 当前文件可在指定场景中作为正式品牌资产 | 是，但必须复制到未来主题并保留出处 |
| `reference-only` | 只用于构图、材质、比例、光线、组件或视觉方向参考 | 否 |
| `excluded-local` | 未批准文案或视觉素材，仅保留在用户本机 | 否，且不得进入 Git |

`docs/design/` 本身不是前端静态资产目录。即使文件标记为 `production-approved`，WordPress 前端也不得直接从该目录加载它。

## 2. 规范性文档

| 文件 | 角色 |
|---|---|
| `UI-STYLE-GUIDE-V1.md` | ForgeTag V1 视觉、响应式、可访问性及 Theme/TagCore 表现层边界 |
| `ASSET-MANIFEST-V1.md` | 源素材身份、准入、完整性与权利状态记录 |

Markdown 文档由 Git 版本控制审计，不适用像素尺寸、Alpha 通道或自身 SHA-256 记录。

## 3. 纳入的图像资产

下列字节数和 SHA-256 针对本 Manifest 基线日期的原始文件。PNG Alpha 状态由 IHDR color type 及 `tRNS` chunk 检查；JPEG 格式不支持 Alpha。

| 文件 | 分类 | 像素尺寸 | 格式 / Alpha | 字节数 | SHA-256 |
|---|---|---:|---|---:|---|
| `homepage.png` | `reference-only` | 816×1927 | PNG RGB 8-bit；无 Alpha | 1,890,077 | `2495bb33dfd528818ccf3cde0e889ceb7320779c019bd7fd1291274bd54b2d64` |
| `tanchuang.png` | `reference-only` | 1254×1254 | PNG RGB 8-bit；无 Alpha | 1,513,939 | `305a9e851ab74911ed6ab898d354ac060c3909fbfb3857b9f4ee956a94397718` |
| `forge-logo.png` | `production-approved` | 300×57 | PNG RGBA 8-bit；有 Alpha | 9,757 | `6e1b446ce5ca667409b6be486c451516f722e22b79d4ae3c8230c53d020fcb75` |
| `forge-logo-light.png` | `reference-only` | 2172×724 | PNG RGB 8-bit；无 Alpha；棋盘格已烧入 | 1,003,582 | `0511d33dad16551e95781b715bec867218b70f2d35062439b6c7a6b80b9d5ac4` |
| `tag1.jpg` | `reference-only` | 1000×1000 | JPEG RGB 8-bit；无 Alpha | 376,559 | `167d3de4f5cfd1dbe828233bd8206f6a7865f494761f4b94ac34b441052512ce` |
| `tag2.png` | `reference-only` | 500×500 | PNG Indexed 8-bit；无 Alpha / `tRNS` | 167,596 | `f2d06056743e867e08005c39f5dc25602e0fd7441ae844cb02e0917b3bb28f46` |
| `tag3.jpg` | `reference-only` | 1000×1000 | JPEG RGB 8-bit；无 Alpha | 227,347 | `ecdc51109865b97292cce485afa2218ef5fd0c7e4b36d573f2fb4397f5f31684` |
| `tag4.jpg` | `reference-only` | 1000×1000 | JPEG RGB 8-bit；无 Alpha | 266,974 | `83233160923222f77bfa83198003a5b20d2b829efba1a16c5cff1eb46bef3550` |
| `forge-smarttag.png` | `reference-only` | 1254×1254 | PNG RGB 8-bit；无 Alpha | 1,276,515 | `a24308f09992bcd1ee13fadf703fa3911cb9d5840887230c3eb8bae186d9936e` |

## 4. 允许与禁止用途

| 文件 | 允许用途 | 禁止用途 | Alt 文本原则 |
|---|---|---|---|
| `homepage.png` | 首页构图、视觉重量、间距和摄影方向参考 | 裁切成 Hero、背景或商品图；引用其营销声明 | 仅在文档中使用描述性链接文本；不进入生产 Alt |
| `tanchuang.png` | 弹窗视觉节奏、层级和组件观感参考 | 定义 TagCore 业务、表单字段或状态；当作运行时背景 | 仅在文档中使用描述性链接文本 |
| `forge-logo.png` | 浅色表面的正式 ForgeTag Logo；未来主题中使用可追溯副本 | 拉伸、改色、重绘、拆分、加影、用 Lucide 替代；超过约 150px CSS 宽度的高密度展示 | `ForgeTag`；同一链接不重复朗读图片 Alt 与隐藏文字 |
| `forge-logo-light.png` | 反白 Logo 的色彩和比例参考 | 上线、抠图、扣除棋盘格、作为透明 Logo 或重制源 | 不进入生产；深色区域回退使用可访问名称 `ForgeTag` 的白色文字字标 |
| `tag1.jpg` | Classic Tag 造型、比例、材质和摄影角度参考 | 任何前端、商品、Hero、Pattern 或 WooCommerce 用途；使用旧 QR/域名/ID | 仅在文档可见时描述为“旧版 Classic Tag 产品参考图” |
| `tag2.png` | Sticker Tag 轮廓、比例和材质参考 | 任何前端、商品、Hero、Pattern 或 WooCommerce 用途；放大伪装高清资产 | 仅在文档可见时描述为“旧版 Sticker Tag 产品参考图” |
| `tag3.jpg` | Classic Tag 的宠物使用场景参考 | 任何前端、商品、Hero、Pattern 或 WooCommerce 用途；使用旧 QR/域名/ID | 仅在文档可见时说明产品与宠物场景，不转录旧 ID |
| `tag4.jpg` | Classic Tag 的行李和钥匙使用场景参考 | 任何前端、商品、Hero、Pattern 或 WooCommerce 用途；使用旧 QR/域名/ID | 仅在文档可见时说明产品与旅行场景，不转录旧 ID |
| `forge-smarttag.png` | Smart Tag 外形、比例和拍摄方向参考 | 任何前端、商品、Hero、Pattern 或 WooCommerce 用途；将 `SmartTag2` 作为已批准型号 | 仅在文档可见时描述为“Smart Tag 产品方向参考图”，不把 `SmartTag2` 当作产品名 |

## 5. 权利与公开仓库状态

用户已于 2026-08-01 确认：

- 本 Manifest 纳入的全部图像具有官网商业使用和必要修改权；
- `tag1.jpg`–`tag4.jpg` 中的旧 QR、旧域名和旧 ID 可以永久保留在公开仓库；
- 权利确认仅解决保存与审计资格，不会覆盖上表的 `reference-only` 生产禁令。

新产品摄影进入主题前，仍必须完成单独的来源、授权、内容和隐私审查。

## 6. 本机排除文件

| 文件 | 分类 | 排除理由 | 处理 |
|---|---|---|---|
| `a1.jpg` | `excluded-local` | 含 `Lifetime Warranty` 等未批准声明 | 保留在用户本机；通过精确 `.gitignore` 规则防止误提交 |
| `ForgeTag文案设计.docx` | `excluded-local` | 含 `trusted by millions`、主动追踪、耐候等未批准文案 | 保留在用户本机；通过精确 `.gitignore` 规则防止误提交 |

不得删除、移动、重命名或修改这两个本机文件。

## 7. RT-314 运行时准入规则

RT-314 只能在以下边界内引入主题资产：

1. WordPress 前端不得直接加载 `docs/design/` 中的任何文件。
2. 主题只能复制 `production-approved` 资产并在 `theme/forge-tag/` 中生成独立的优化衍生文件。
3. `reference-only` 文件不得出现在 CSS、Template、Template Part、Pattern、Block 或 WooCommerce 页面中。
4. 新产品摄影不得暴露真实可路由 Tag ID、旧域名或个人数据，也不得数字伪造 QR/ID。
5. Manrope、Inter、Lucide 及各自许可证由 RT-314 单独锁定来源、版本和 SHA-256；RT-313 不下载它们。
6. 品牌首页可以使用设计系统和布局基线开发，但最终视觉验收需要不含旧域名、旧 ID 或真实二维码的 Classic Tag、Sticker Tag 和 Smart Tag 生产摄影。

## 8. 完整性与交付策略

- 纳入的原始图像保留原像素、文件名和字节，不压缩、不重绘、不覆盖 QR、不伪造六位 ID。
- V1 使用普通 Git 保存，不启用 Git LFS；当前文件总量和单文件大小在仓库可接受范围内。
- 任何修改源图字节的后续工作必须生成新文件并更新 Manifest，不得静默覆盖本基线。
- 本工单不新增或修改 API、Block、Route、Option、Schema、Hook、产品状态、TagCore 或 WooCommerce 行为。
