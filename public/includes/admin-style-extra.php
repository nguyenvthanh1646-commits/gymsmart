<?php
/** admin-style-extra.php — CSS bổ sung cho trang nhật ký, thống kê, bài viết (dùng cùng class adm- của admin-style.php). Include sau admin-style.php. */
?>
<style>
.adm-filters{display:grid;grid-template-columns:1.4fr 1.2fr 1fr 1fr auto;gap:10px 12px;padding:14px;border-bottom:1px solid var(--adm-line);align-items:end}
.adm-filters .adm-ff{display:flex;flex-direction:column;gap:6px;min-width:0;margin:0;padding:0}
/* !important: giao diện chung của site có style cho thẻ label/input riêng, ở đây cần cả 4 ô lọc giống hệt nhau */
.adm-filters .adm-flabel{display:block !important;margin:0 !important;padding:0 !important;font-size:.82rem !important;line-height:1.2 !important;font-weight:600 !important;color:var(--adm-muted) !important}
.adm-filters .adm-input,.adm-filters .adm-select{margin:0 !important;width:100%;height:46px;-webkit-appearance:none;appearance:none}
.adm-filters .adm-select{padding-right:38px}
.adm-filters .adm-btn{height:46px;margin:0}
.adm-pager{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:12px 14px;border-top:1px solid var(--adm-line);font-size:.92rem;color:var(--adm-muted)}
.adm-act-badge{background:#eef2ff;color:#3730a3}
.adm-act-badge.is-del{background:#fef2f2;color:#991b1b}
.adm-act-badge.is-new{background:#f0fdf4;color:#166534}
.adm-mono{font-variant-numeric:tabular-nums;white-space:nowrap}
.adm-chart{position:relative;height:280px}
.adm-chart--tall{height:340px}
.adm-three{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:16px}
.adm-card-pad h2{margin:0 0 12px;font-size:1.05rem}
.adm-data{margin-top:10px;font-size:.88rem;color:var(--adm-muted)}
.adm-data table{width:100%;border-collapse:collapse;margin-top:6px}
.adm-data td,.adm-data th{padding:4px 8px;border-top:1px solid var(--adm-line);text-align:left}
.adm-hint-box{background:#fffaeb;border:1px solid #fedf89;color:#93370d;border-radius:10px;padding:12px 16px;margin-bottom:16px}
.adm-b-FILE{background:#f1f5f9;color:#475569}
.adm-b-PUBLISHED{background:#f0fdf4;color:#166534}
.adm-b-DRAFT{background:#fffaeb;color:#93370d}
.adm-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.adm-form-grid .adm-field--full{grid-column:1/-1}
.adm-code{font-family:ui-monospace,Consolas,monospace;font-size:14px}
@media (max-width:900px){.adm-filters{grid-template-columns:1fr 1fr}.adm-form-grid{grid-template-columns:1fr}}
@media (max-width:560px){.adm-filters{grid-template-columns:1fr}}
</style>
<style>
/* Bộ lọc dạng viên thuốc (trang đơn HLV): mục đang chọn nền tối, chữ trắng */
.adm-todo a.is-on{background:var(--ink);border-color:var(--ink);color:#fff}
.adm-todo a{min-height:44px;display:inline-flex;align-items:center}
</style>
