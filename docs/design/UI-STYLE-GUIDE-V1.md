# ForgeTag WordPress 主题 UI 设计指引 V1

- **状态：** ForgeTag V1 可版本控制的消费者视觉基线
- **文档版本：** 1.2
- **文档语言：** 中文说明；生产界面文案使用可翻译的 US English
- **适用范围：** 品牌官网、主题负责的 WooCommerce 表现层、未来账户页面的主题外壳，以及 TagCore 表现层集成
- **本文件不实施：** WordPress 主题、TagCore 样式修改、路由、表单、数据访问或业务行为
- **规范性资产清单：** [ForgeTag V1 设计资产 Manifest](./ASSET-MANIFEST-V1.md)

### 品牌与开发命名边界

- **ForgeTag** 是面向消费者的正式品牌。网站导航、Logo 替代文本、产品名称、页面标题、CTA、帮助内容、交易邮件主题和其他消费者可见文案均使用 ForgeTag。
- 批准的 Logo 图稿可以保留其内置的 `FORGE` 字样；这属于 ForgeTag 品牌标志，不代表普通界面文案可以把产品名缩写为 FORGE。
- **ReturnTag** 仅作为仓库、架构、PHP 命名空间、数据库前缀、选项、Hook、内部日志与开发文档中的项目名称。不得因为消费者品牌变化而重命名 `ReturnTag\\TagCore`、`returntag_`、`tagcore` 或既有稳定技术合同。
- 消费者界面不得显示 “ReturnTag” 作为品牌名；开发标识也不得泄漏到页面标题、可见错误、URL 文案或无障碍名称。
- 本设计决策不改变 TagCore 的职责边界、数据模型、安全控制或项目内部规范。PRD、架构文档和 ADR 中尚存的消费者用语需要由单独授权的产品文档变更收敛；本文件不修改这些冻结文档。

## 1. 视觉来源与效力

本指引依据用户提供的视觉效果图与源素材制定。以下列表仅表示文档纳入，不等于获得主题运行时准入：

- [品牌首页效果图](./homepage.png)：视觉参考，不是运行时图片。
- [桌面弹窗效果图](./tanchuang.png)：视觉参考，不定义 TagCore 业务。
- [ForgeTag 标准 Logo](./forge-logo.png)：V1 当前唯一可直接上线的正式品牌图像。
- [ForgeTag 淡色 Logo](./forge-logo-light.png)：reference-only，棋盘格已烧入 RGB。
- [Classic Tag 产品图 1](./tag1.jpg)：reference-only。
- [Sticker Tag 产品图](./tag2.png)：reference-only。
- [Classic Tag 产品图 2](./tag3.jpg)：reference-only。
- [Classic Tag 产品图 3](./tag4.jpg)：reference-only。
- [Smart Tag 产品参考图](./forge-smarttag.png)：reference-only；`SmartTag2` 尚未批准为消费者型号名称。

效果图用于确定构图、视觉重量、间距、产品摄影、色彩层级、卡片语言和弹窗观感，不构成产品、安全或业务合同。

当效果图或消费者品牌资产与 ReturnTag 项目已批准的产品、架构、隐私、可访问性或技术命名规则冲突时，以仓库正式文档为准。尤其需要遵守：

- 面向消费者的正式品牌名称为 **ForgeTag**；ReturnTag 只用于项目开发语境。
- 品牌官网必须提供清晰的 **Activate** 和 **Report** 两个入口。
- 桌面入口打开 TagCore 负责的弹窗，移动端使用 TagCore 负责的全屏页面。
- 初始入口仅要求输入六位 Tag ID。
- 实际 Tag 状态由 TagCore 服务端解析，不能由 CTA 或主题决定。
- 智能查找网络与 ForgeTag QR 找回是两套相互独立的系统。
- ForgeTag 不读取 Apple 或 Google 账户、设备、配对、电池或位置数据。

所有原始效果图和产品图片必须保持不变。`docs/design/` 是设计源资料目录，不是 WordPress 前端资产目录；主题不得从该目录直接加载任何文件。未来主题只能复制生产批准资产并生成独立、可追溯的优化衍生图；reference-only 文件不得进入 CSS、模板、Pattern 或 WooCommerce 页面。

## 2. 品牌方向

### 2.1 设计命题

ForgeTag 应呈现为一个值得信任的高端旅行安全品牌：冷静、精确，并且让用户在紧张情况下也能迅速理解下一步。

核心视觉组合：

- 冷白和浅金属质感表面；
- 高对比黑色文字；
- 单一且克制的安全红；
- 精密线性图标；
- 真实产品和旅行摄影；
- 充足留白和克制阴影；
- 直接、实用的界面文案。

界面不能显得玩具化、过度装饰、过度技术化，也不能呈现为通用 SaaS 模板。

### 2.2 标志性元素：“Return Route”

品牌标志性图形是红色路径标记：有意义的序号点、精细连接线或方向箭头，用于解释真实流程。

允许使用：

- “How it works” 有序步骤；
- 真实多步骤流程的进度；
- 与 CTA 直接相关的方向提示；
- 已确认的找回或流程里程碑。

禁止用于：

- 随意装饰；
- 不代表顺序的章节编号；
- 背景纹理；
- 虚假的流程进度。

### 2.3 Logo 规则

`forge-logo.png` 是 V1 当前唯一可直接上线的正式品牌图像，用于浅色表面，图像尺寸为 300×57px，带 Alpha 通道。`forge-logo-light.png` 只是深色表面的视觉参考，尺寸为 2172×724px，棋盘格背景已烧入无 Alpha 通道的 RGB 图片，不能直接上线，也不得作为抠图或重制源。

- 浅色 Header 使用 `forge-logo.png`，不得拉伸、改色、加描边、加阴影或拆分图形与字标。
- 深色 Footer 使用纯白文字字标 `ForgeTag` 作为 V1 回退；不处理、抠取或显示 `forge-logo-light.png` 中的棋盘格背景。
- 300×57px 标准 Logo 只适用于约 150px CSS 宽度以内的常规密度场景；高密度大尺寸展示需要 SVG 或更高分辨率透明源文件。
- Logo 周围最小安全距离取图形标记高度的 0.5 倍；Header 建议显示高度为 28–32px，移动 Header 为 24–28px。
- Logo 链接的无障碍名称和图像替代文本统一为 `ForgeTag`；同一链接内不得同时重复朗读图片 Alt 和隐藏文字。
- 不得描摹、近似重绘或用 Lucide 图标替代品牌 Logo。
- `forgetag`、`tag-core` 等废弃技术名称不得进入代码或消费者界面；`ForgeTag` 是本文件批准的消费者品牌写法。
- 正式发布前仍需补充 SVG、单色版本、反白版本和商标安全区原始规范。

## 3. 色彩系统

### 3.1 核心 Token

| 语义名称 | 色值 | 用途 |
|---|---:|---|
| Forge Red | `#DC1117` | 主 CTA、选中路径标记、关键品牌强调 |
| Red Hover | `#B90D13` | 红色主控件 Hover 和 Pressed 状态 |
| Ink | `#15171A` | 主文字、深色表面、强边界 |
| Graphite | `#4F555E` | 次级文字、辅助标签 |
| Cloud | `#F4F5F7` | 页面背景、交替区块背景 |
| Surface | `#FFFFFF` | 卡片、表单、弹窗、导航 |
| Line | `#D9DDE3` | 分隔线、输入框边框、卡片边界 |
| Focus Inner | `#FFFFFF` | 自适应 Focus Ring 内层 |
| Focus Outer | `#15171A` | 自适应 Focus Ring 外层 |

建议的 WordPress `theme.json` preset slug：

```text
forge-red
forge-red-hover
ink
graphite
cloud
surface
line
focus-inner
focus-outer
```

未来由 `theme.json` 生成的 CSS 自定义属性构成主题 Token 合同。本文件本身不创建这些 preset。

### 3.2 色彩层级

- 页面主体以 Ink 和 Surface 为主。
- Cloud 用于区分内容区块，不额外增加装饰性色彩。
- Forge Red 只用于主操作、真实有序路径和关键品牌强调。
- 一个屏幕通常只保留一个占主导地位的红色操作。
- 次级控件使用 Ink 文字、Line 边框和 Surface 背景。
- 不得把蓝、绿、紫、彩色渐变或发光效果作为通用品牌装饰。
- 成功、警告和错误状态需要后续组件状态规范；不得把 Forge Red 同时用于所有状态。

### 3.3 最低对比要求

- 正文：至少满足 WCAG AA `4.5:1`。
- 大字号文字和有意义的 UI 图形：至少 `3:1`。
- Surface 文字置于 Forge Red：约 `5.08:1`。
- Surface 文字置于 Red Hover：约 `6.71:1`。
- Ink 置于 Surface：约 `17.96:1`。
- Ink 置于 Cloud：约 `16.46:1`。

## 4. Focus 系统

### 4.1 对现有蓝色的评估

现有 TagCore 公共样式使用 `#1D4ED8` 作为 Focus 色。它在浅色表面上表现良好，但不适合作为 V1 主题唯一的通用 Focus Token。

| 相邻颜色 | 与 `#1D4ED8` 的对比度 |
|---|---:|
| Surface `#FFFFFF` | `6.70:1` |
| Cloud `#F4F5F7` | `6.14:1` |
| Ink `#15171A` | `2.68:1` |
| Forge Red `#DC1117` | `1.32:1` |

蓝色还会在已经确定的黑、白、灰、红体系中引入第四种强调色，削弱品牌一致性。

### 4.2 V1 决策：黑白双层 Focus Ring

主题控件以及未来完成品牌集成的 TagCore 控件使用黑白双层 Focus Ring：

- 内层：2px Surface；
- 外层：3px Ink；
- 控件与 Ring 的间距：2px；
- 必须通过轮廓形状和面积呈现，不能只改变颜色。

白色内层可在 Ink、红色和摄影背景上保持可见；Ink 外层可在 Surface 和 Cloud 上保持可见。

参考行为：

```css
:focus-visible {
	outline: 2px solid var(--wp--preset--color--focus-inner);
	outline-offset: 2px;
	box-shadow: 0 0 0 5px var(--wp--preset--color--focus-outer);
}

@media (forced-colors: active) {
	:focus-visible {
		outline: 3px solid CanvasText;
		outline-offset: 3px;
		box-shadow: none;
	}
}
```

未来实现必须使用主题或组件作用域，不得通过主题加入不受控的全局 Reset。

本指引不修改现有 TagCore 的 `#1D4ED8`。后续必须由单独授权的表现层集成工单完成统一，同时保留 TagCore 独立路由的安全回退样式。

## 5. 字体系统

### 5.1 字体角色

| 角色 | 字体 | 字重 | 用途 |
|---|---|---:|---|
| Display | Manrope Variable | 700–800 | Hero、H1、主要章节表达 |
| Body and UI | Inter Variable | 400–700 | 正文、导航、按钮、标签和数据 |
| 开发回退 | System sans-serif | 400–700 | 仅在本地字体加载失败时使用 |

建议字体栈：

```css
--forge-font-display: "Manrope", "Arial", sans-serif;
--forge-font-body: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
```

两套字体必须以批准许可的 WOFF2 文件自托管。不得从 Google Fonts 或其他第三方域名在运行时加载。

生产交付规则：

- 字体运行时文件随未来主题交付，不放入 `docs/design`，也不由 TagCore 提供。
- 主题目录确定后，将文件放置于 `assets/fonts/manrope/` 和 `assets/fonts/inter/`，许可证放置于 `assets/licenses/`。
- Manrope 使用官方 Roman Variable 字体，声明 `font-weight: 200 800`；当前界面实际使用 700–800。
- Inter 使用官方 Roman Variable 字体，声明 `font-weight: 100 900`；当前界面实际使用 400–700。
- 未使用斜体前不打包 Italic；不得为减少文件大小而删除实际需要的字符。
- 下载时锁定来源版本或提交，记录 SHA-256，并随主题保留两套 SIL Open Font License 1.1 文本。
- 使用 `font-display: swap`；只预加载首屏实际使用的 Roman WOFF2，不预加载所有字重或后续页面字体。
- 字体加载失败不得导致按钮、Tag ID、OTP 或导航文字被裁切；系统回退字体必须通过同一响应式验收。

批准来源：

- Manrope：[Google Fonts 官方仓库 `ofl/manrope`](https://github.com/google/fonts/tree/main/ofl/manrope) 及随附 OFL 1.1。
- Inter：[Inter 官方发布](https://github.com/rsms/inter/releases)及随附 OFL 1.1。

### 5.2 字号层级

| 样式 | 字号 | 行高 | 字重 | 字距 |
|---|---|---|---:|---:|
| Hero | `clamp(2.75rem, 5vw, 4.5rem)` | 1.04 | 800 | `-0.04em` |
| H1 | `clamp(2.5rem, 4vw, 4rem)` | 1.08 | 800 | `-0.035em` |
| H2 | `clamp(2rem, 3vw, 2.75rem)` | 1.12 | 750 | `-0.03em` |
| H3 | `clamp(1.25rem, 2vw, 1.5rem)` | 1.25 | 700 | `-0.02em` |
| Body large | `1.125rem` | 1.6 | 400 | normal |
| Body | `1rem` | 1.55 | 400 | normal |
| Label | `1rem` | 1.35 | 650 | normal |
| Small | `0.875rem` | 1.5 | 400–600 | normal |
| Eyebrow | `0.75rem` | 1.3 | 700 | `0.16em` |

规则：

- 正文每行控制在约 45–72 个字符。
- 控件和标题使用 Sentence case。
- 全大写只用于短 Eyebrow 和紧凑 Utility Label。
- 长段落不得居中排版。
- Tag ID、OTP、数量和账户数据使用等宽数字。
- 生产界面文案使用 US English 并保持可翻译。

## 6. 间距、栅格与构图

### 6.1 间距

采用 4px 基准：

```text
4, 8, 12, 16, 24, 32, 48, 64, 96, 128
```

- 控件内部间距：8–16px。
- 卡片内边距：24–32px。
- 弹窗内边距：桌面 48px；紧凑桌面/平板 24–32px；移动端 20–24px。
- 区块纵向间距：桌面 96px；平板 64px；移动端 48–64px。
- 除非有记录的视觉校正理由，不得引入一次性间距值。

### 6.2 栅格

| 视口 | 栏数 | 最小栏距 | 常用外边距 |
|---|---:|---:|---:|
| Mobile `<640px` | 4 | 16px | 20px |
| Tablet `640–1023px` | 8 | 20px | 32px |
| Desktop `1024–1279px` | 12 | 24px | 48px |
| Wide `≥1280px` | 12 | 24–32px | 64px |

- 内容最大宽度：1440px。
- 编辑内容不能横跨整个栅格宽度。
- 产品摄影可有意识地占据更宽区域。
- 不允许任何横向溢出。
- WordPress Admin Bar 断点需要独立于视觉断点进行测试。

### 6.3 圆角与层级

| 组件 | 圆角 |
|---|---:|
| 输入框和标准按钮 | 8px |
| 小型卡片 | 16px |
| Hero 媒体框 | 24px |
| 桌面弹窗 | 24px |
| Pill 或状态 Chip | 999px |

阴影：

```text
Card:  0 8px 24px rgb(21 23 26 / 7%)
Modal: 0 24px 72px rgb(15 15 15 / 20%)
```

阴影只用于解释真实的视觉堆叠。普通内容区块优先使用留白或 Line 分隔，不添加无意义阴影。

## 7. 动效

- 标准 Hover 和控件过渡：180ms。
- 弹窗进入和退出：240ms。
- 推荐缓动：`cubic-bezier(.2, .8, .2, 1)`。
- 弹窗可以结合透明度和轻微的 `0.98` 到 `1` 缩放。
- 不得为大幅背景图增加动画、视差或零散滚动揭示。
- 动画不能延迟用户访问表单或导航。
- `prefers-reduced-motion: reduce` 下移除 Transform，只保留即时或极短的透明度变化。

## 8. 摄影、Logo 与图标

### 8.1 摄影

效果图确定的摄影方向：

- 黑色或 Graphite 产品置于明亮中性环境；
- 行李、机场、包袋、钥匙、钱包和宠物等真实场景；
- 清晰的实体细节、真实材质和可控反射；
- 冷中性商业调色；
- 为相邻文字预留空间，不在图片中烧录文字。

实现要求：

- 使用有许可的原始资产，不得裁切效果图作为生产图片。
- 使用 WordPress 响应式图片尺寸和 `srcset`。
- 优先 AVIF 或 WebP，并提供合适回退。
- 通过明确的裁切焦点保留主体产品。
- Hero 使用宽幅横图；同组产品卡片和生活方式卡片使用统一比例。
- 有信息价值的图片提供有效 Alt；纯装饰图片使用空 Alt。

已提供产品摄影的收敛结果：

| 文件 | 产品族 | 尺寸 | V1 分类与允许用途 |
|---|---|---:|---|
| `tag1.jpg` | Classic Tag | 1000×1000 | reference-only；仅参考造型、比例、材质与摄影角度 |
| `tag2.png` | Sticker | 500×500 | reference-only；仅参考 Sticker 的轮廓与材质 |
| `tag3.jpg` | Classic Tag | 1000×1000 | reference-only；仅参考宠物使用场景 |
| `tag4.jpg` | Classic Tag | 1000×1000 | reference-only；仅参考行李和钥匙使用场景 |
| `forge-smarttag.png` | Smart Tag | 1254×1254 | reference-only；仅参考外形与拍摄方向，不批准 `SmartTag2` 型号名称 |

`tag1.jpg`–`tag4.jpg` 记录了旧版实体产品，画面中含有旧域名、旧编码和可扫描 QR。用户已确认这些内容可以保留在公开仓库，但该确认不会使它们获得生产运行时准入。`forge-smarttag.png` 中的 `SmartTag2` 也不是已批准的消费者型号名称。

- 上述五张 reference-only 图片不得用于 Hero、商品卡、产品页、WooCommerce、Pattern、CSS 背景或任何前端运行时资产。
- 不得把照片中的旧编码作为六位 Tag ID、激活 ID 或 Finder 流程示例。
- 新生产摄影不得暴露真实可路由 Tag ID、旧域名或个人数据，也不得数字伪造、手绘或局部覆盖 QR/ID 来制造不存在的产品版本。
- Hero 需要新的宽幅 ForgeTag 生产摄影，商品卡也需要不含旧识别信息的 Classic Tag、Sticker Tag 和 Smart Tag 生产照片。
- 对未来生产批准图片，主题应生成独立 AVIF/WebP 衍生图：Hero 建议 1600/1200/768px，商品卡建议 800/600/400px，同时保留浏览器兼容回退。

### 8.2 图标

- 通用界面图标优先使用 [Lucide 官方图标库](https://github.com/lucide-icons/lucide)；品牌、支付和第三方平台 Logo 不由 Lucide 替代。
- 标准线宽：1.5px。
- 默认颜色：Ink；辅助颜色：Graphite。
- 红色只用于选中状态、路径节点和关键动作。
- 首页、弹窗、Shop 和 Dashboard 必须使用同一图标家族。
- 禁止 Emoji、CSS 绘图、文本符号、临时手绘 SVG 或混用不同图标家族。
- 未来主题通过 `lucide-static` 开发依赖锁定版本，构建时只复制批准白名单中的 SVG；不得从 CDN 加载完整图标库。
- 随主题保留 Lucide ISC 许可证；源自 Feather 的图标同时遵守仓库许可证中列出的 MIT 条款。
- 装饰性图标使用 `aria-hidden="true"`；只有图标的按钮必须提供可访问名称，不得依赖 `title` 作为唯一名称。

V1 图标白名单：

```text
menu, x, user, shopping-bag, search, arrow-right, chevron-down,
qr-code, mail-check, shield-check, circle-check, circle-alert,
loader-circle, key-round, luggage, package, smartphone
```

新增图标必须先确认语义和视觉必要性；不得因为版面空白而添加装饰图标。

## 9. 基础组件

### 9.1 按钮

**Primary**

- Forge Red 背景，Surface 文字。
- 最小高度 48px。
- 水平内边距 24–28px。
- 8px 圆角。
- Hover 使用 Red Hover。
- Pressed 状态可有轻微内收视觉反馈，但不能缩小点击范围。

**Secondary**

- Surface 背景。
- Ink 文字。
- 1px Line 边框。
- Hover 使用 Cloud。

**Tertiary**

- 无常驻容器。
- 根据层级使用 Ink 或 Forge Red 文字。
- Hover/Focus 必须增加下划线或方向标识，不能只改颜色。

**Disabled**

- 必须保持可读。
- 允许降低对比，但不得用过低的整体 Opacity。
- 移除指针暗示，并暴露正确的 Disabled 语义状态。

**Loading**

- 保持按钮宽度稳定。
- 使用具体文案，例如 `Sending code…`。
- 不得只显示无标签 Spinner。

一个组件或弹窗步骤内只保留一个主导 Primary Button。

### 9.2 链接

- 正文链接常驻下划线。
- 导航链接可不常驻下划线，但必须有清晰 Hover 和 Focus。
- 禁止使用 `Click here`。
- 使用 `View setup guide`、`Contact support` 等明确描述结果的链接文本。

### 9.3 输入框

- 桌面和移动端最小高度均为 52px。
- Label 始终显示在输入框上方。
- 默认 1px Line 边框；Hover 使用 Graphite 边框。
- Surface 背景。
- 8px 圆角。
- Help 和 Error 文案需要与控件建立程序化关联。
- Error 状态同时使用图标、文字和边框，不能只显示红色。
- Placeholder 只是示例，不能替代 Label。

Tag ID 示例：

```text
Label: Tag ID
Help: Enter the six-character ID printed on the tag.
Example: A7R2W9
```

不得展示 `A1B2-C3D4`、Claim ID、隐藏激活码或其他 ID 模型。

### 9.4 卡片

- 使用 Surface、Line 边框和克制阴影。
- 产品卡片优先展示名称、简短定位、真实产品图和已验证卖点。
- 生活方式卡片可使用全幅摄影，但底部标签必须保持足够对比。
- 除非 Badge 表达 `New` 等真实状态，不添加悬浮装饰 Badge。
- 卡片点击区、链接和按钮之间不得形成嵌套冲突。

## 10. 首页规范

### 10.1 Header

桌面端：

- Surface 背景，单行导航。
- 左侧：ForgeTag Logo；浅色 Header 使用 `forge-logo.png`。
- 中部：产品和教育内容导航。
- 右侧：Shop 入口以及清晰的 Activate、Report 入口。
- 所有控件最小点击范围 44px。

移动端：

- Logo、菜单控件和最关键的产品流程入口保持可见。
- 展开导航支持键盘操作，且不会错误锁定焦点。
- Activate 和 Report 保持为两个不同动作。

V1 不采用弹窗效果图背景中的促销通知条。配送、退货、折扣和保证文案必须先获得 WooCommerce 与法务规则批准。

### 10.2 Hero

- 桌面使用约 42% 文案、58% 产品摄影的双栏构图。
- 产品图片是主视觉。
- 文案简洁并左对齐。
- 推荐层级：
  - 简短红色 Eyebrow；
  - 直接的找回价值主张；
  - 一段辅助说明；
  - `Activate my tag` Primary；
  - `Report a found tag` Secondary；
  - `How it works` 作为较低层级锚点链接。
- 用户选择动作之前，不显示 Tag ID 输入框。

移动端：

- 文案先于产品摄影。
- 按钮可以堆叠或换行，但不能缩小到最低点击范围以下。
- 图片必须保留产品焦点，不能被裁成失去意义的装饰条。

### 10.3 “How ForgeTag works”

- 使用三个真实有序步骤。
- 由于内容确实有顺序，可以使用 Return Route 节点与连接线。
- 整体置于 Cloud 页面上的 Surface 卡片。
- 桌面可轻微覆盖 Hero 底部。
- 移动端取消覆盖并垂直堆叠。

批准的概念顺序：

1. 激活 Tag。
2. Finder 扫描 QR。
3. ForgeTag 提供隐私联系渠道。

不得在底层能力没有支持时声称已完成定位、确认找回或确认通知送达。

### 10.4 产品系列

只能展示：

- Sticker；
- Classic Tag；
- Smart Tag。

桌面使用一致的三卡片组，移动端垂直排列。Smart Tag 文案必须清楚区分：

- 外部兼容智能查找网络；
- ForgeTag QR 找回。

不得暗示 ForgeTag 读取位置、配对、设备、账户或电池数据。

### 10.5 两条找回路径

使用产品/旅行摄影与两项简洁说明组成分栏内容：

- Smart finding network；
- ForgeTag QR recovery。

两套系统相互独立。效果图中的 “Forge mobile app” 和 “audio tracking” 必须替换为批准的兼容 App 描述。

### 10.6 使用场景

采用统一的摄影网格展示合理场景，例如：

- Luggage；
- Wallet；
- Keys；
- Pets。

标签必须位于可控的高对比区域，不能在图片上覆盖长文案。

### 10.7 品牌证明、评价与信任条

- 只使用已经批准且可追溯的证据。
- 评价卡片需要真实评价、归属规则和审核机制。
- `Millions sold`、`TSA Approved`、`Trusted travel brand`、`Free shipping`、`30-day guarantee` 等声明在获得验证前不进入 V1。
- 优先使用可验证的产品原则，例如隐私联系、不要求 Finder 安装 App、独立 QR 找回。

### 10.8 Footer

- Ink 背景、Surface 文字和清晰的信息层级。
- 当前 `forge-logo-light.png` 只作参考，不处理或抠取其棋盘格背景；V1 深色 Footer 使用纯白 `ForgeTag` 文字字标回退。
- Product、Resources、Company、Privacy、Support 分组只有在目标页面存在时才显示。
- 邮件订阅属于次级界面，需要单独的同意和营销实现。
- 深色背景上的 Focus 依靠双层 Ring 的白色内层保持可见。

## 11. TagCore 弹窗规范

### 11.1 职责边界

主题可以输出 TagCore 集成触发器和批准的品牌 Token。TagCore 负责：

- 桌面弹窗和移动全屏产品流程适配器；
- Tag ID 验证与标准化；
- Tag 和 Batch 查询；
- Tag 状态解析；
- 身份验证、授权、Nonce、隐私和限流；
- Activation、Finder、Owner、Invalid、Suspended、Retired、Unavailable 和 Fail-closed 状态。

主题不得查询 TagCore 表、复制产品表单、推导 Tag 状态或执行任何业务写入。

### 11.2 桌面弹窗

- 适用于 768px 及以上视口。
- 遮罩：`rgb(15 15 15 / 56%)`。
- 支持时使用约 6px 的克制 Backdrop Blur。
- 最大宽度：680px。
- 最大高度：`calc(100dvh - 64px)`。
- Surface 背景、24px 圆角、Modal 阴影。
- 内边距：标准桌面 48px；紧凑桌面/平板 24–32px。
- 弹窗内容可以内部滚动，背景页面不能滚动。
- Close 控件至少 44×44px，并具有可访问名称。

### 11.3 移动全屏

低于 768px：

- 使用 TagCore 全屏页面或全屏 Surface；
- 宽度和最小高度覆盖动态视口；
- 移除桌面弹窗圆角和网页背景遮罩；
- 保留 Safe Area；
- Title、Close/Back、Form 和 Validation 均不得产生横向滚动。

QR 扫码直接进入 `/t/{tag_id}`，不得再次要求输入 Tag ID。

### 11.4 初始手动输入内容

Activation 意图：

```text
Title: Activate your ForgeTag
Introduction: Enter the six-character ID printed on your tag.
Primary action: Continue
```

Report 意图：

```text
Title: Report a found ForgeTag
Introduction: Enter the six-character ID printed on the tag you found.
Primary action: Continue
```

意图只改变初始标题和引导，不得覆盖 TagCore 服务端解析的状态。

效果图中的物品类型卡片、Description、Consent Checkbox 和故障帮助链接只作为组件视觉参考，不属于当前首个弹窗步骤。

### 11.5 弹窗可访问性

- 适用时使用 `role="dialog"` 和 `aria-modal="true"`。
- 可见 Title 和 Description 必须与 Dialog 建立关联。
- 打开时将焦点移动到有意义的首个控件。
- 打开期间键盘焦点保留在弹窗内。
- 在安全允许关闭时支持 Escape。
- 关闭后恢复到原始触发控件。
- 弹窗激活时将背景设为 Inert。
- 保留语义 Label 和动态 Validation Announcement。
- 不得因点击弹窗内部内容而意外关闭。
- 不得通过 iframe 嵌入独立路由。
- JavaScript 不可用时，触发器必须回退到可用的 TagCore 页面。

## 12. Shop、Checkout 与 Dashboard 继承规则

V1 不批准这些页面的具体布局。在确定页面结构前需要新的效果图。

它们继承：

- 色彩和 Focus Token；
- 字体和间距；
- Button、Link、Form、Card 和 Icon 规则；
- 44px 最小点击范围；
- Surface、Cloud、Ink 的区块层级；
- 图片和可访问性规则。

它们不自动继承：

- 首页 Hero 构图；
- 重叠的 “How it works” 卡片；
- 弹窗专用尺寸；
- 未验证的促销、评价、配送或保证文案。

WooCommerce 模板只负责表现层，不得把 Order、Order Item、Shipment 或 Tracking Number 映射到 Tag ID。

## 13. 主题与 TagCore Token 集成

未来 Block Theme 应通过 `theme.json` 暴露批准的 Palette、Font Family、Font Size、Spacing 和 Layout Width。

单独授权的 TagCore 表现层工单可以将以下 Plugin-scoped 值映射到品牌 preset：

```text
--returntag-color-accent
--returntag-color-background
--returntag-color-text
--returntag-color-focus
```

要求：

- `/t/{tag_id}` 在没有当前主题时仍必须可用。
- 移除主题不能移除 TagCore 的样式或可访问性。
- 主题不得通过 Global CSS Reset 改变 TagCore 控件。
- Token 集成必须明确并进行版本控制。
- 本 V1 文件不声称现有 TagCore 已经继承主题样式。

## 14. 文案与声明规则

### Do

- 从最终用户视角书写。
- 使用主动、直接的 Label。
- 同一动作在整个流程中保持同一名称。
- 错误状态明确说明下一步。
- 准确使用 `private email relay` 或批准的营销语言。
- 所有生产字符串保持可翻译。

### Don’t

- 在消费者界面把 ReturnTag 当作品牌名，或在批准 Logo 之外把 ForgeTag 随意缩写为 FORGE。
- 声称端到端加密。
- 声称 ForgeTag 已验证 Apple 或 Google 配对。
- 声称 ForgeTag 读取位置、设备、账户、电池或网络数据。
- 声称 Finder 必须安装 App。
- 暗示 Activate 或 Report 按钮决定 Tag 状态。
- 显示另一方的私人邮箱。
- 添加未经批准的附件、位置地图、配对控件或追踪 Dashboard。
- 在没有证据时发布商业、认证、配送、评价或保证声明。

## 15. UI Do / Don’t

| Do | Don’t |
|---|---|
| 一个区域只保留一个主导红色操作 | 用多个红色按钮竞争注意力 |
| 使用真实产品和旅行摄影 | 裁切效果图或制作假产品素材 |
| 主题只复制生产批准资产 | 从 `docs/design/` 加载图片或使用 reference-only 文件 |
| Return Route 只用于真实流程 | 添加没有意义的编号装饰 |
| 使用黑白双层 Focus Ring | 只靠颜色变化表达 Focus |
| 表单始终显示 Label | 使用 Placeholder 代替 Label |
| 先选择 Activate/Report 再打开弹窗 | 品牌页常驻 Tag ID 输入框 |
| 移动端使用全屏输入 | 把桌面弹窗压缩成移动小卡片 |
| 由 TagCore 解析状态 | 由主题或 CTA 决定流程 |
| 独立表述 QR 与智能网络 | 声称 ForgeTag 读取外部位置数据 |
| 保持表面安静、精确 | 添加渐变、发光、玻璃效果或过度动效 |

## 16. 验收清单

### 视觉

- [ ] 布局反映效果图方向，并统一使用 ForgeTag 消费者品牌；ReturnTag 仅保留在开发合同中。
- [ ] 浅色 Logo 清晰且未被改色、拉伸或重绘。
- [ ] 深色表面使用批准的白色文字字标回退，不处理或显示棋盘格 Logo。
- [ ] 黑、白、灰、红保持为核心视觉色盘。
- [ ] 字体遵守 Display/Body 角色划分。
- [ ] 产品摄影保持为品牌主资产，且不使用 RT-313 标记的 reference-only 源图。
- [ ] 红色只用于有层级或流程意义的位置。
- [ ] Card、Radius、Spacing 和 Shadow 遵守本指引。

### 响应式

- [ ] 320px 及以上不存在横向溢出。
- [ ] 首页堆叠后仍保持信息层级。
- [ ] 低于 768px 时桌面弹窗改为全屏。
- [ ] QR 入口不会再次要求输入 ID。
- [ ] 200% 文本缩放下仍可使用。

### 可访问性

- [ ] 所有交互目标至少 44×44px。
- [ ] 正文和 UI 对比度满足 WCAG AA。
- [ ] 双层 Focus Ring 在 Surface、Cloud、Ink、红色和摄影背景上均可见。
- [ ] Forced-colors 使用系统 Focus 色。
- [ ] 键盘顺序与视觉顺序一致。
- [ ] Dialog 焦点被正确限制并恢复。
- [ ] 遵守 Reduced Motion。
- [ ] 图片具有正确的 Alt 处理。

### 架构与隐私

- [ ] 主题中不存在 TagCore 业务逻辑。
- [ ] Tag ID 示例只使用六位合法字符。
- [ ] Activate/Report 意图不覆盖服务端状态。
- [ ] 不使用 iframe 嵌入标准路由。
- [ ] 不引入 Apple 或 Google 账户、设备、配对、电池或位置声明。
- [ ] 不暴露 Owner 或 Finder 邮箱。
- [ ] TagCore 敏感页面保留 no-cache、no-referrer、no-index、本地资产和无不必要追踪控制。
- [ ] 主题、Pattern、CSS 和 WooCommerce 模板不直接引用 `docs/design/`。

## 17. 待补生产资产

正式主题达到视觉完成状态之前，需要提供或批准：

- ForgeTag 反白 Logo、单色 SVG 和高分辨率生产源文件；当前 `forge-logo.png` 是 V1 唯一直接上线的品牌图像。
- Manrope、Inter 自托管 WOFF2 与许可记录；
- 不含风险 QR、旧域名或旧编码的生产产品摄影，以及移动端安全裁切；
- 锁定版本的 Lucide 图标资产与许可证；
- 有证据的产品、认证、配送、退货、评价和信任文案；
- Shop、Checkout 和 Dashboard 页面效果图。

在这些资产到位之前，本指引可以确定布局和系统行为，但品牌首页不能通过解除 reference-only 标记来进行最终视觉验收。不允许近似重绘 Logo、伪造产品摄影、数字伪造 QR/Tag ID，或使用不受支持的营销声明。
