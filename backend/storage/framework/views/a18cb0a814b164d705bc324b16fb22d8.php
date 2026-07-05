<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($company->name); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            line-height: 1.5;
            color: #1e293b;
            padding: 0;
        }

        /* Header bar */
        .header-bar {
            background-color: #163D8E;
            color: #ffffff;
            text-align: center;
            padding: 12px 20px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .content {
            padding: 30px 45px 30px 45px;
        }

        /* Company name */
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #163D8E;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .mst-line {
            font-size: 10.5px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .status-line {
            font-size: 10.5px;
            margin-bottom: 30px;
        }
        .status-active {
            color: #079569;
            font-weight: bold;
            font-style: italic;
        }

        /* Section titles */
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #163D8E;
            margin-bottom: 16px;
            margin-top: 30px;
        }

        /* Info list */
        .info-list {
            margin-bottom: 10px;
        }
        .info-item {
            margin-bottom: 6px;
            line-height: 1.6;
        }
        .info-label {
            font-style: italic;
            color: #374151;
        }
        .info-value {
            color: #1e293b;
        }

        /* Industry table */
        .industry-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 10.5px;
        }
        .industry-table thead th {
            background-color: #f1f5f9;
            border-top: 2px solid #163D8E;
            border-bottom: 1px solid #cbd5e1;
            padding: 10px 10px;
            text-align: left;
            font-weight: bold;
            color: #1e293b;
            font-size: 10px;
        }
        .industry-table thead th:first-child {
            width: 50px;
            text-align: center;
        }
        .industry-table thead th:last-child {
            width: 80px;
            text-align: center;
        }
        .industry-table tbody td {
            padding: 10px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            color: #334155;
        }
        .industry-table tbody td:first-child {
            text-align: center;
            color: #475569;
        }
        .industry-table tbody td:last-child {
            text-align: center;
            color: #1e293b;
        }
        .industry-table tbody tr.primary td {
            font-weight: bold;
            color: #1e293b;
        }

        /* Footer */
        .footer {
            margin-top: 35px;
            font-size: 10px;
            color: #64748b;
            font-style: italic;
        }
    </style>
</head>
<body>
    
    <div class="header-bar">
        THÔNG TIN ĐĂNG KÝ DOANH NGHIỆP
    </div>

    <div class="content">
        
        <div class="company-name"><?php echo e($company->name); ?></div>

        
        <div class="mst-line">Mã số thuế: <?php echo e($company->mst); ?></div>

        
        <div class="status-line">
            <span class="info-label">Trạng thái:</span>
            <span class="status-active"><?php echo e($company->status ?? 'Đang hoạt động'); ?></span>
        </div>

        
        <div class="section-title">1. Thông tin chung</div>

        <div class="info-list">
            <?php if($company->operation_date): ?>
            <div class="info-item">
                <span class="info-label">Ngày hoạt động:</span>
                <span class="info-value"><?php echo e($company->operation_date->format('Y-m-d')); ?></span>
            </div>
            <?php endif; ?>

            <?php if($company->short_name): ?>
            <div class="info-item">
                <span class="info-label">Loại hình doanh nghiệp:</span>
                <span class="info-value"><?php echo e($company->short_name); ?></span>
            </div>
            <?php endif; ?>

            <?php if($company->managing_tax_authority): ?>
            <div class="info-item">
                <span class="info-label">Cơ quan quản lý thuế:</span>
                <span class="info-value"><?php echo e($company->managing_tax_authority); ?></span>
            </div>
            <?php endif; ?>

            <div class="info-item">
                <span class="info-label">Địa chỉ đăng ký:</span>
                <span class="info-value"><?php echo e($company->address ?? 'N/A'); ?></span>
            </div>

            <?php if($company->phone): ?>
            <div class="info-item">
                <span class="info-label">Số điện thoại:</span>
                <span class="info-value"><?php echo e($company->phone); ?></span>
            </div>
            <?php endif; ?>

            <?php if($company->representative): ?>
            <div class="info-item">
                <span class="info-label">Đại diện pháp luật:</span>
                <span class="info-value"><?php echo e($company->representative); ?></span>
            </div>
            <?php endif; ?>

            <?php
                $primaryIndustry = collect($industries)->firstWhere('is_primary', true);
                $primaryDesc = $primaryIndustry['description'] ?? ($industries[0]['description'] ?? '');
            ?>

            <?php if($primaryDesc): ?>
            <div class="info-item">
                <span class="info-label">Ngành nghề chính:</span>
                <span class="info-value"><?php echo e($primaryDesc); ?></span>
            </div>
            <?php endif; ?>
        </div>

        
        <?php if(count($industries) > 0): ?>
        <div class="section-title">2. Danh mục ngành nghề kinh doanh (<?php echo e(count($industries)); ?> ngành)</div>

        <table class="industry-table">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tên ngành nghề kinh doanh</th>
                    <th>Mã ngành</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $industries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $industry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="<?php echo e(($industry['is_primary'] ?? false) ? 'primary' : ''); ?>">
                    <td><?php echo e($index + 1); ?></td>
                    <td><?php echo e($industry['description'] ?? '-'); ?><?php echo e(($industry['is_primary'] ?? false) ? ' (Ngành chính)' : ''); ?></td>
                    <td><?php echo e($industry['code'] ?? '-'); ?></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>

        
        <div class="footer">
            Ngày xuất báo cáo: <?php echo e($generatedAt); ?>

        </div>
    </div>
</body>
</html>
<?php /**PATH /var/www/resources/views/pdf/company-report.blade.php ENDPATH**/ ?>