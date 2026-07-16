/**
 * Google Apps Script - Webhook nhận dữ liệu DN từ Laravel scraper.
 * 
 * CẬP NHẬT: Thêm cột "Nguồn" để phân biệt dữ liệu từ masothue.com vs tramasothue.com.vn
 * 
 * HƯỚNG DẪN DEPLOY:
 * 1. Mở Google Apps Script: https://script.google.com/macros/library/d/1zRCpQtl2dZHQs_anFPK4Cl-CRmq37p6GjtnXZYz09VoJeZy97e7K1Nh1/2
 * 2. Thay thế toàn bộ Code.gs bằng nội dung file này
 * 3. Deploy → New Deployment → Web App → Execute as Me → Anyone
 * 4. Copy URL mới (nếu URL thay đổi thì update .env GOOGLE_SHEET_WEBHOOK_URL)
 */

const SPREADSHEET_ID = ''; // <-- Paste Google Sheet ID tại đây
const RETENTION_DAYS = 7;  // Giữ tab 7 ngày (theo yêu cầu khách)

/**
 * POST handler - nhận data từ Laravel GoogleSheetService
 */
function doPost(e) {
  try {
    const data = JSON.parse(e.postData.contents);
    const sheet = getOrCreateTodaySheet();
    
    // Append row với cột "Nguồn" mới
    sheet.appendRow([
      formatAsText(data.mst || ''),          // A: MST (text, căn trái)
      data.name || '',                        // B: Tên DN
      formatPhone(data.phone || ''),          // C: SĐT (text, căn trái)
      data.address || '',                     // D: Địa chỉ
      data.representative || '',              // E: Đại diện
      data.operation_date || '',              // F: Ngày hoạt động
      data.industry || '',                    // G: Ngành nghề chính
      data.province || '',                    // H: Tỉnh/TP
      data.source || 'masothue.com',          // I: Nguồn ← CỘT MỚI
      data.time || '',                        // J: Giờ quét
    ]);

    // Format cột MST và SĐT thành text, căn trái
    const lastRow = sheet.getLastRow();
    sheet.getRange(lastRow, 1).setNumberFormat('@');  // MST = text
    sheet.getRange(lastRow, 3).setNumberFormat('@');  // SĐT = text

    return ContentService.createTextOutput(
      JSON.stringify({ status: 'ok' })
    ).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(
      JSON.stringify({ status: 'error', message: err.message })
    ).setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * GET handler (cho health check)
 */
function doGet(e) {
  return ContentService.createTextOutput(
    JSON.stringify({ status: 'ok', message: 'Webhook is running' })
  ).setMimeType(ContentService.MimeType.JSON);
}

/**
 * Lấy hoặc tạo sheet tab cho ngày hôm nay.
 * Header bao gồm cột "Nguồn" mới.
 */
function getOrCreateTodaySheet() {
  const ss = SpreadsheetApp.openById(SPREADSHEET_ID);
  const today = Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', 'dd-MM-yyyy');
  
  let sheet = ss.getSheetByName(today);
  
  if (!sheet) {
    sheet = ss.insertSheet(today, 0);
    
    // Header row
    const headers = [
      'MST', 'Tên doanh nghiệp', 'SĐT', 'Địa chỉ', 
      'Đại diện', 'Ngày HĐ', 'Ngành nghề', 'Tỉnh/TP', 
      'Nguồn',  // ← CỘT MỚI
      'Giờ quét'
    ];
    
    const headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setValues([headers]);
    
    // Style header
    headerRange.setBackground('#163D8E');
    headerRange.setFontColor('#FFFFFF');
    headerRange.setFontWeight('bold');
    headerRange.setHorizontalAlignment('center');
    
    // Freeze header row + enable filter
    sheet.setFrozenRows(1);
    headerRange.createFilter();
    
    // Set column widths
    sheet.setColumnWidth(1, 120);  // MST
    sheet.setColumnWidth(2, 280);  // Tên DN
    sheet.setColumnWidth(3, 120);  // SĐT
    sheet.setColumnWidth(4, 300);  // Địa chỉ
    sheet.setColumnWidth(5, 150);  // Đại diện
    sheet.setColumnWidth(6, 100);  // Ngày HĐ
    sheet.setColumnWidth(7, 250);  // Ngành nghề
    sheet.setColumnWidth(8, 120);  // Tỉnh/TP
    sheet.setColumnWidth(9, 180);  // Nguồn ← MỚI
    sheet.setColumnWidth(10, 80);  // Giờ quét

    // Format cột MST + SĐT thành text
    sheet.getRange('A:A').setNumberFormat('@');
    sheet.getRange('C:C').setNumberFormat('@');
    
    // Căn trái cột MST + SĐT
    sheet.getRange('A:A').setHorizontalAlignment('left');
    sheet.getRange('C:C').setHorizontalAlignment('left');
    
    // Xóa tab cũ quá RETENTION_DAYS ngày
    cleanupOldSheets(ss);
  }
  
  return sheet;
}

/**
 * Xóa các tab sheet cũ hơn RETENTION_DAYS ngày.
 */
function cleanupOldSheets(ss) {
  const sheets = ss.getSheets();
  const now = new Date();
  
  sheets.forEach(function(sheet) {
    const name = sheet.getName();
    // Parse dd-MM-yyyy
    const parts = name.split('-');
    if (parts.length === 3) {
      const sheetDate = new Date(parts[2], parts[1] - 1, parts[0]);
      const diffDays = Math.floor((now - sheetDate) / (1000 * 60 * 60 * 24));
      
      if (diffDays > RETENTION_DAYS && sheets.length > 1) {
        ss.deleteSheet(sheet);
      }
    }
  });
}

/**
 * Format số điện thoại: phải đủ 10 số, bắt đầu bằng 0.
 * Nếu có 2 số → "số1 - số2"
 */
function formatPhone(phone) {
  if (!phone) return '';
  
  // Tách nhiều số (separator: , hoặc / hoặc ;)
  const phones = phone.split(/[,\/;]+/).map(function(p) { return p.trim(); });
  
  const valid = phones.filter(function(p) {
    // Chỉ giữ số bắt đầu bằng 0 và đủ 10 chữ số
    const digits = p.replace(/\D/g, '');
    return digits.length === 10 && digits.startsWith('0');
  }).map(function(p) {
    return p.replace(/\D/g, ''); // Trả về chỉ số
  });
  
  return valid.join(' - ');
}

/**
 * Format MST thành plain text (tránh Google Sheet tự convert sang number)
 */
function formatAsText(value) {
  return value.toString();
}
