<?php
/**
 * admin-style.php — CSS riêng cho khu quản trị.
 *
 * Mọi class đều bắt đầu bằng "adm-" nên không đụng vào style.css chung của E.
 * Dùng: include __DIR__ . '/../includes/admin-style.php'; (sau header.php)
 * Desktop: bảng. Điện thoại (< 720px): mỗi dòng bảng thành một thẻ.
 */
?>
<style>
.adm-wrap{--adm-accent:#ff5a1f;--adm-accent-dark:#e04a12;--adm-line:#e5e7eb;--adm-muted:#6b7280;--adm-danger:#b91c1c;
  max-width:1040px;margin:0 auto;padding:24px 16px 96px}
.adm-wrap *{box-sizing:border-box}
.adm-wrap [hidden]{display:none !important}
.adm-head{display:flex;flex-wrap:wrap;gap:12px 16px;align-items:center;justify-content:space-between;margin-bottom:20px}
.adm-head h1{margin:0;font-size:1.6rem;line-height:1.2}
.adm-sub{margin:4px 0 0;color:var(--adm-muted);font-size:.95rem}
.adm-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:44px;padding:0 18px;border-radius:10px;
  border:1px solid transparent;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;background:var(--adm-accent);color:#fff}
.adm-btn:hover{background:var(--adm-accent-dark)}
.adm-btn-ghost{background:#fff;color:#111;border-color:var(--adm-line)}
.adm-btn-ghost:hover{background:#f3f4f6}
.adm-btn-danger{background:#fff;color:var(--adm-danger);border-color:#fecaca}
.adm-btn-danger:hover{background:#fef2f2}
.adm-btn-solid-danger{background:var(--adm-danger)}
.adm-btn-solid-danger:hover{background:#991b1b}
.adm-btn-sm{min-height:38px;padding:0 14px;font-size:.92rem}

.adm-toast{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-weight:500;border:1px solid}
.adm-toast-ok{background:#f0fdf4;color:#166534;border-color:#bbf7d0}
.adm-toast-error{background:#fef2f2;color:#991b1b;border-color:#fecaca}

.adm-panel{background:#fff;border:1px solid var(--adm-line);border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.adm-tools{display:grid;grid-template-columns:1fr 170px 170px;gap:10px;padding:14px;border-bottom:1px solid var(--adm-line)}
.adm-input,.adm-select,.adm-textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;
  font:inherit;font-size:16px;background:#fff;color:#111}
.adm-textarea{min-height:100px;resize:vertical;line-height:1.5}
.adm-input:focus,.adm-select:focus,.adm-textarea:focus{outline:2px solid var(--adm-accent);outline-offset:1px;border-color:transparent}
.adm-count{padding:10px 16px;color:var(--adm-muted);font-size:.9rem;border-bottom:1px solid var(--adm-line)}

.adm-table{width:100%;border-collapse:collapse}
.adm-table th{text-align:left;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--adm-muted);
  padding:10px 16px;background:#f9fafb;border-bottom:1px solid var(--adm-line)}
.adm-table td{padding:12px 16px;border-bottom:1px solid #f0f1f3;vertical-align:middle}
.adm-table tr:last-child td{border-bottom:0}
.adm-table tbody tr:hover{background:#fafafa}
.adm-name{font-weight:600}
.adm-id{color:var(--adm-muted);font-size:.85rem}
.adm-actions{display:flex;gap:8px;justify-content:flex-end}
.adm-actions form{margin:0}

.adm-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.8rem;font-weight:600;background:#f3f4f6;color:#374151;white-space:nowrap}
.adm-b-BEGINNER{background:#dcfce7;color:#166534}
.adm-b-INTERMEDIATE{background:#fef3c7;color:#92400e}
.adm-b-ADVANCED{background:#fee2e2;color:#991b1b}
.adm-b-CARDIO{background:#e0f2fe;color:#075985}
.adm-b-RESISTANCE{background:#ede9fe;color:#5b21b6}
.adm-video-yes{color:#166534;font-weight:600}
.adm-video-no{color:#9ca3af}
.adm-empty{padding:40px 16px;text-align:center;color:var(--adm-muted)}

/* ===== Điện thoại: mỗi dòng bảng thành một thẻ ===== */
@media (max-width:720px){
  .adm-tools{grid-template-columns:1fr 1fr}
  .adm-tools .adm-input{grid-column:1 / -1}
  .adm-table thead{display:none}
  .adm-table,.adm-table tbody{display:block}
  .adm-table tr{display:block;padding:14px 16px;border-bottom:1px solid var(--adm-line)}
  .adm-table tr:last-child{border-bottom:0}
  .adm-table td{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:5px 0;border:0}
  .adm-table td[data-label]::before{content:attr(data-label);color:var(--adm-muted);font-size:.85rem}
  .adm-table td.adm-cell-name{display:block;padding-bottom:8px}
  .adm-table td.adm-cell-actions{padding-top:10px}
  .adm-actions{width:100%}
  .adm-actions>*{flex:1}
  .adm-actions .adm-btn{width:100%}
  .adm-cell-id{display:none !important}
}

/* ===== Hộp xác nhận xóa ===== */
.adm-dialog{border:0;border-radius:16px;padding:24px;max-width:420px;width:calc(100% - 32px);box-shadow:0 20px 50px rgba(0,0,0,.25)}
.adm-dialog::backdrop{background:rgba(17,24,39,.5)}
.adm-dialog h2{margin:0 0 8px;font-size:1.2rem}
.adm-dialog p{margin:0 0 20px;color:var(--adm-muted)}
.adm-dialog-actions{display:flex;gap:10px;justify-content:flex-end}
.adm-dialog-actions form{margin:0}

/* ===== Form thêm/sửa ===== */
.adm-form-section{padding:20px 20px 4px;border-bottom:1px solid var(--adm-line)}
.adm-form-section:last-of-type{border-bottom:0}
.adm-form-section h2{margin:0 0 14px;font-size:1.02rem}
.adm-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0 14px}
.adm-field{margin-bottom:16px}
.adm-field label{display:block;font-weight:600;font-size:.92rem;margin-bottom:6px}
.adm-hint{margin:6px 0 0;color:var(--adm-muted);font-size:.85rem}
.adm-errors{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:12px 16px 12px 32px;margin-bottom:16px}
.adm-errors li{margin:2px 0}
.adm-preview{display:none;margin-top:10px;max-width:260px;border-radius:10px;border:1px solid var(--adm-line);aspect-ratio:16/9;object-fit:cover;background:#f3f4f6}
.adm-preview.is-on{display:block}
.adm-bar{display:flex;gap:10px;justify-content:flex-end;padding:16px 20px;background:#f9fafb;border-top:1px solid var(--adm-line);border-radius:0 0 14px 14px}
@media (max-width:720px){
  .adm-grid{grid-template-columns:1fr}
  .adm-form-section{padding:16px 16px 2px}
  .adm-bar{position:fixed;left:0;right:0;bottom:0;border-radius:0;box-shadow:0 -4px 12px rgba(0,0,0,.08);z-index:20;padding:12px 16px}
  .adm-bar>*{flex:1}
}

/* ===== Thanh điều hướng quản trị ===== */
body{overflow-x:hidden}
.container:has(> .adm-nav){max-width:none;padding:0}   /* khu quản trị dùng toàn bề ngang, thanh menu tối kéo hết chiều rộng */
.adm-nav{background:#111827;color:#fff}
.adm-nav-in{max-width:1040px;margin:0 auto;padding:0 16px;display:flex;align-items:center;gap:4px;overflow-x:auto;white-space:nowrap}
.adm-nav a.adm-nav-brand{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;font-size:.78rem;color:#fdba74;margin-right:14px;padding:12px 0;border-bottom:0}
.adm-nav a.adm-nav-brand:hover{color:#fff}
.adm-nav a{color:#d1d5db;text-decoration:none;padding:14px 12px;font-size:.95rem;border-bottom:3px solid transparent}
.adm-nav a:hover{color:#fff}
.adm-nav a.is-active{color:#fff;border-bottom-color:#ff5a1f}
.adm-nav .adm-nav-out{margin-left:auto;font-size:.85rem;color:#9ca3af}

/* ===== Tổng quan: thẻ số liệu + biểu đồ thanh ===== */
.adm-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.adm-stat{background:#fff;border:1px solid var(--adm-line);border-radius:14px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.adm-stat-num{font-size:1.9rem;font-weight:700;line-height:1.1}
.adm-stat-label{color:var(--adm-muted);font-size:.88rem;margin-top:4px}
.adm-stat-warn .adm-stat-num{color:#b45309}
.adm-two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.adm-card-pad{padding:16px 18px}
.adm-card-pad h2{margin:0 0 14px;font-size:1.02rem}
.adm-meter-row{display:grid;grid-template-columns:90px 1fr 32px;align-items:center;gap:10px;margin-bottom:10px;font-size:.92rem}
.adm-meter-track{height:10px;background:#f3f4f6;border-radius:999px;overflow:hidden}
.adm-meter-fill{height:100%;background:var(--adm-accent);border-radius:999px}
.adm-meter-num{text-align:right;font-weight:600}
.adm-list{list-style:none;margin:0;padding:0}
.adm-list li{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f0f1f3;font-size:.93rem}
.adm-list li:last-child{border-bottom:0}
.adm-list small{color:var(--adm-muted)}
.adm-todo{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.adm-todo a{display:inline-block;padding:8px 14px;border-radius:999px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;text-decoration:none;font-size:.9rem;font-weight:600}
@media (max-width:720px){
  .adm-stats{grid-template-columns:repeat(2,1fr)}
  .adm-two{grid-template-columns:1fr}
}
.adm-ok{background:#dcfce7;color:#166534}
.adm-warn{background:#fef3c7;color:#92400e}
.adm-b-ADMIN{background:#fee2e2;color:#991b1b}
.adm-b-USER{background:#f3f4f6;color:#374151}

/* ===== Dải chào đầu trang Tổng quan ===== */
.adm-hero{position:relative;overflow:hidden;display:flex;flex-wrap:wrap;gap:20px 24px;align-items:center;justify-content:space-between;
  color:#fff;border-radius:20px;padding:30px 32px;margin-bottom:18px;
  background:radial-gradient(520px 260px at 92% 0%,rgba(255,90,31,.38),transparent 65%),radial-gradient(420px 260px at 0% 100%,rgba(70,104,230,.28),transparent 65%),linear-gradient(135deg,#070b18,#101a35)}
.adm-hero::before{content:"";position:absolute;inset:0;pointer-events:none;
  background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px);
  background-size:44px 44px;-webkit-mask-image:linear-gradient(180deg,#000,transparent 90%);mask-image:linear-gradient(180deg,#000,transparent 90%)}
.adm-hero>*{position:relative}
.adm-hero__chip{display:inline-block;padding:5px 12px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);font-size:.74rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#c7d0e6}
.adm-hero h1{margin:12px 0 4px;font-size:clamp(1.5rem,3.4vw,2.1rem);line-height:1.25;letter-spacing:-.01em}
.adm-hero p{margin:0;color:#c7d0e6}
.adm-hero__cta{display:flex;flex-wrap:wrap;gap:10px}
.adm-btn-light{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.22)}
.adm-btn-light:hover{background:rgba(255,255,255,.16)}
.adm-stat{display:flex;gap:14px;align-items:center}
.adm-stat-ico{flex:none;width:46px;height:46px;border-radius:13px;display:grid;place-items:center;color:#fff;background:linear-gradient(135deg,#ff8a3d,#ff4b0f);box-shadow:0 8px 16px rgba(255,90,31,.25)}
.adm-stat-warn .adm-stat-ico{background:linear-gradient(135deg,#fbbf24,#d97706);box-shadow:0 8px 16px rgba(217,119,6,.25)}
@media (max-width:720px){.adm-hero{padding:22px 20px}.adm-hero__cta{width:100%}.adm-hero__cta .adm-btn{flex:1}.adm-stat{flex-direction:column;align-items:flex-start;gap:10px}}
</style>
