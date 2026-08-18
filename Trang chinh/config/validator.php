<?php
/**
 * GLOWDRINKS / DUCKDUCK INPUT VALIDATOR & SANITIZER HELPER
 */

class Validator {
    
    /**
     * Validate Vietnamese Fullname (Min 2 chars, letters and spaces only)
     */
    public static function validateFullname($name) {
        $name = trim($name);
        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 60) {
            return "Họ và tên phải từ 2 đến 60 ký tự!";
        }
        // Allow Vietnamese Unicode letters and spaces
        if (!preg_match('/^[a-zA-ZàáảãạâầấẩẫậăằắẳẵặèéẻẽẹêềếểễệđìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵÀÁẢÃẠÂẦẤẨẪẬĂẰẮẲẴẶÈÉẺẼẸÊỀẾỂỄỆĐÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰỲÝỶỸỴ\s]+$/u', $name)) {
            return "Họ và tên không chứa số hoặc ký tự đặc biệt!";
        }
        return null;
    }

    /**
     * Validate Vietnamese Phone Number (10 digits starting with 0)
     */
    public static function validatePhone($phone) {
        $phone = preg_replace('/\s+/', '', trim($phone));
        if (!preg_match('/^(0[3|5|7|8|9])[0-9]{8}$/', $phone)) {
            return "Số điện thoại không hợp lệ! (Ví dụ: 0987654321 hoặc 0312345678)";
        }
        return null;
    }

    /**
     * Validate Email Address
     */
    public static function validateEmail($email) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Địa chỉ Email không đúng định dạng!";
        }
        return null;
    }

    /**
     * Validate Delivery Address (Min 10 characters)
     */
    public static function validateAddress($address) {
        $address = trim($address);
        if (mb_strlen($address, 'UTF-8') < 10) {
            return "Địa chỉ giao hàng quá ngắn! Vui lòng ghi rõ số nhà, tên đường, phường/xã.";
        }
        if (mb_strlen($address, 'UTF-8') > 250) {
            return "Địa chỉ không vượt quá 250 ký tự!";
        }
        return null;
    }

    /**
     * Validate Payment Method Selection
     */
    public static function validatePaymentMethod($method) {
        $allowed = ['COD', 'QR_CODE', 'MOMO', 'VNPAY'];
        if (!in_array($method, $allowed, true)) {
            return "Phương thức thanh toán không hợp lệ!";
        }
        return null;
    }

    /**
     * Validate Quantity (1 - 99)
     */
    public static function validateQuantity($qty) {
        $qty = intval($qty);
        if ($qty < 1 || $qty > 99) {
            return "Số lượng phải từ 1 đến 99!";
        }
        return null;
    }
}
?>
