<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder tạo các Role và Permission cốt lõi bằng tiếng Việt.
 *
 * Role chính:
 *  - Super Admin: Toàn quyền hệ thống
 *  - Quản lý kho: Quản lý sản phẩm, kho, tồn kho
 *  - Quản lý bán hàng: Quản lý khách hàng, đơn bán
 *  - Quản lý mua hàng: Quản lý nhà cung cấp, đơn mua
 *  - Kế toán: Quản lý thu chi, đối soát
 *  - Nhân viên: Quyền cơ bản xem dữ liệu
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache quyền của spatie
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ─────────────────────────────────────────────────────────────
        // 1. DANH SÁCH PERMISSIONS CỐT LÕI (Tiếng Việt)
        // ─────────────────────────────────────────────────────────────
        $permissions = [
            // Quản lý kho & sản phẩm
            'xem_danh_sach_kho',
            'tao_kho',
            'cap_nhat_kho',
            'xoa_kho',
            'xem_danh_sach_san_pham',
            'tao_san_pham',
            'cap_nhat_san_pham',
            'xoa_san_pham',
            'xem_danh_sach_danh_muc',
            'quan_ly_danh_muc',

            // Quản lý đơn bán
            'xem_don_ban',
            'tao_don_ban',
            'cap_nhat_don_ban',
            'huy_don_ban',
            'duyet_don_ban',
            'xuat_kho',

            // Quản lý đơn mua
            'xem_don_mua',
            'tao_don_mua',
            'cap_nhat_don_mua',
            'duyet_don_mua',
            'nhap_kho',
            'huy_don_mua',

            // Lịch sử tồn kho & báo cáo (Phase 2)
            'xem_lich_su_ton_kho',
            'xuat_bao_cao',

            // Quản lý khách hàng
            'xem_danh_sach_khach_hang',
            'tao_khach_hang',
            'cap_nhat_khach_hang',
            'xoa_khach_hang',

            // Quản lý nhà cung cấp
            'xem_danh_sach_nha_cung_cap',
            'tao_nha_cung_cap',
            'cap_nhat_nha_cung_cap',
            'xoa_nha_cung_cap',

            // Tài chính
            'xem_bao_cao_tai_chinh',
            'xem_bao_cao_loi_nhuan',
            'thu_tien',
            'chi_tien',
            'doi_soat_ngan_hang',

            // Quản trị hệ thống
            'quan_ly_nguoi_dung',
            'quan_ly_vai_tro',
            'xem_nhat_ky_hoat_dong',

            // ─────────────────────────────────────────────────────────────
            // HR & Payroll — Nhân sự & Lương (Phase 5)
            // ─────────────────────────────────────────────────────────────

            // Phòng ban
            'xem_danh_sach_phong_ban',
            'tao_phong_ban',
            'cap_nhat_phong_ban',
            'xoa_phong_ban',

            // Chức vụ
            'xem_danh_sach_chuc_vu',
            'tao_chuc_vu',
            'cap_nhat_chuc_vu',
            'xoa_chuc_vu',

            // Nhân viên
            'xem_danh_sach_nhan_vien',
            'xem_nhan_vien',
            'tao_nhan_vien',
            'cap_nhat_nhan_vien',
            'xoa_nhan_vien',
            'xem_luong_nhan_vien',   // Chỉ HR Manager + Chính NV mới được xem
            'cap_nhat_luong_nhan_vien',

            // Chấm công
            'xem_danh_sach_cham_cong',
            'tao_cham_cong',
            'cap_nhat_cham_cong',
            'xoa_cham_cong',
            'xem_cham_cong_ca_nhan', // NV tự xem record của mình
            'tao_cham_cong_ca_nhan', // NV tự chấm công

            // Nghỉ phép
            'xem_danh_sach_nghi_phep',
            'tao_don_nghi_phep',
            'cap_nhat_don_nghi_phep',
            'xoa_don_nghi_phep',
            'xem_don_nghi_phep_ca_nhan',
            'duyet_don_nghi_phep',
            'tu_choi_don_nghi_phep',
            'huy_don_nghi_phep',

            // Luật hoa hồng
            'xem_danh_sach_luat_hoa_hong',
            'tao_luat_hoa_hong',
            'cap_nhat_luat_hoa_hong',
            'xoa_luat_hoa_hong',

            // Hoa hồng
            'xem_danh_sach_hoa_hong',
            'xem_hoa_hong_ca_nhan',
            'tao_hoa_hong',
            'cap_nhat_hoa_hong',
            'xoa_hoa_hong',
            'duyet_hoa_hong',
            'hoan_tien_hoa_hong', // reverse

            // Tính lương
            'xem_danh_sach_tinh_luong',
            'tao_dot_tinh_luong',
            'tinh_luong',
            'duyet_tinh_luong',
            'chi_tra_luong',
            'huy_dot_tinh_luong',
            'xem_chi_tiet_phieu_luong', // HR Manager + chính NV
            'dieu_chinh_phieu_luong',
        ];

        // Tạo permissions (bỏ qua nếu đã tồn tại)
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
            );
        }

        // ─────────────────────────────────────────────────────────────
        // 2. TẠO CÁC ROLE
        // ─────────────────────────────────────────────────────────────

        // Super Admin - Có tất cả quyền (Dùng API của Spatie)
        $superAdmin = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'web'],
        );
        $superAdmin->syncPermissions(Permission::all());

        // Quản lý kho
        $quanLyKho = Role::firstOrCreate(
            ['name' => 'Quản lý kho', 'guard_name' => 'web'],
        );
        $quanLyKho->syncPermissions([
            'xem_danh_sach_kho',
            'tao_kho',
            'cap_nhat_kho',
            'xem_danh_sach_san_pham',
            'tao_san_pham',
            'cap_nhat_san_pham',
            'xem_danh_sach_danh_muc',
            'quan_ly_danh_muc',
            'xem_don_ban',
            'xem_don_mua',
            'nhap_kho',
            'xuat_kho',
            'xem_lich_su_ton_kho',
            'xuat_bao_cao',
        ]);

        // Quản lý bán hàng
        $quanLyBanHang = Role::firstOrCreate(
            ['name' => 'Quản lý bán hàng', 'guard_name' => 'web'],
        );
        $quanLyBanHang->syncPermissions([
            'xem_danh_sach_san_pham',
            'xem_danh_sach_khach_hang',
            'tao_khach_hang',
            'cap_nhat_khach_hang',
            'xem_don_ban',
            'tao_don_ban',
            'cap_nhat_don_ban',
            'duyet_don_ban',
            'xuat_kho',
            // HR: xem hoa hồng cá nhân
            'xem_danh_sach_hoa_hong',
            'xem_hoa_hong_ca_nhan',
            'xem_danh_sach_cham_cong',
            'xem_cham_cong_ca_nhan',
            'xem_danh_sach_nghi_phep',
            'xem_don_nghi_phep_ca_nhan',
            'tao_don_nghi_phep',
        ]);

        // Quản lý mua hàng
        $quanLyMuaHang = Role::firstOrCreate(
            ['name' => 'Quản lý mua hàng', 'guard_name' => 'web'],
        );
        $quanLyMuaHang->syncPermissions([
            'xem_danh_sach_san_pham',
            'xem_danh_sach_nha_cung_cap',
            'tao_nha_cung_cap',
            'cap_nhat_nha_cung_cap',
            'xem_don_mua',
            'tao_don_mua',
            'cap_nhat_don_mua',
            'duyet_don_mua',
            'nhap_kho',
            // HR: tự phục vụ
            'xem_danh_sach_cham_cong',
            'xem_cham_cong_ca_nhan',
            'xem_danh_sach_nghi_phep',
            'xem_don_nghi_phep_ca_nhan',
            'tao_don_nghi_phep',
        ]);

        // Kế toán
        $keToan = Role::firstOrCreate(
            ['name' => 'Kế toán', 'guard_name' => 'web'],
        );
        $keToan->syncPermissions([
            'xem_don_ban',
            'xem_don_mua',
            'xem_danh_sach_khach_hang',
            'xem_danh_sach_nha_cung_cap',
            'xem_bao_cao_tai_chinh',
            'xem_bao_cao_loi_nhuan',
            'thu_tien',
            'chi_tien',
            'doi_soat_ngan_hang',
            'xem_lich_su_ton_kho',
            'xuat_bao_cao',
            // HR: xem payroll để đối soát lương
            'xem_danh_sach_tinh_luong',
            'xem_chi_tiet_phieu_luong',
            'xem_danh_sach_hoa_hong',
            'xem_luong_nhan_vien',
        ]);

        // Nhân viên - Chỉ xem
        $nhanVien = Role::firstOrCreate(
            ['name' => 'Nhân viên', 'guard_name' => 'web'],
        );
        $nhanVien->syncPermissions([
            'xem_danh_sach_san_pham',
            'xem_danh_sach_danh_muc',
            'xem_danh_sach_kho',
            'xem_danh_sach_khach_hang',
            'xem_danh_sach_nha_cung_cap',
            'xem_don_ban',
            'xem_don_mua',
            'xem_lich_su_ton_kho',
            // Tự phục vụ: chấm công + nghỉ phép của chính mình
            'xem_cham_cong_ca_nhan',
            'tao_cham_cong_ca_nhan',
            'xem_don_nghi_phep_ca_nhan',
            'tao_don_nghi_phep',
            'cap_nhat_don_nghi_phep', // chỉ record đang PENDING của mình
            'huy_don_nghi_phep',       // chỉ record đang PENDING/APPROVED của mình
            'xem_hoa_hong_ca_nhan',
        ]);

        // ─────────────────────────────────────────────────────────────
        // 3. HR ROLES (Phase 5)
        // ─────────────────────────────────────────────────────────────

        // HR Manager — Toàn quyền module Nhân sự
        $hrManager = Role::firstOrCreate(
            ['name' => 'HR Manager', 'guard_name' => 'web'],
        );
        $hrManager->syncPermissions([
            // Xem
            'xem_danh_sach_phong_ban',
            'xem_danh_sach_chuc_vu',
            'xem_danh_sach_nhan_vien',
            'xem_danh_sach_cham_cong',
            'xem_danh_sach_nghi_phep',
            'xem_danh_sach_luat_hoa_hong',
            'xem_danh_sach_hoa_hong',
            'xem_danh_sach_tinh_luong',
            // Tạo / Cập nhật / Xóa
            'tao_phong_ban',
            'cap_nhat_phong_ban',
            'xoa_phong_ban',
            'tao_chuc_vu',
            'cap_nhat_chuc_vu',
            'xoa_chuc_vu',
            'tao_nhan_vien',
            'cap_nhat_nhan_vien',
            'xoa_nhan_vien',
            'xem_luong_nhan_vien',
            'cap_nhat_luong_nhan_vien',
            'tao_cham_cong',
            'cap_nhat_cham_cong',
            'xoa_cham_cong',
            'tao_luat_hoa_hong',
            'cap_nhat_luat_hoa_hong',
            'xoa_luat_hoa_hong',
            'tao_hoa_hong',
            'cap_nhat_hoa_hong',
            'xoa_hoa_hong',
            'duyet_hoa_hong',
            'hoan_tien_hoa_hong',
            'tao_dot_tinh_luong',
            'tinh_luong',
            'duyet_tinh_luong',
            'chi_tra_luong',
            'huy_dot_tinh_luong',
            'xem_chi_tiet_phieu_luong',
            'dieu_chinh_phieu_luong',
            // Nghỉ phép: duyệt + từ chối
            'duyet_don_nghi_phep',
            'tu_choi_don_nghi_phep',
            'huy_don_nghi_phep',
        ]);

        // HR Staff — Tạo/sửa nhân viên, quản lý chấm công, nghỉ phép
        $hrStaff = Role::firstOrCreate(
            ['name' => 'HR Staff', 'guard_name' => 'web'],
        );
        $hrStaff->syncPermissions([
            // Xem
            'xem_danh_sach_phong_ban',
            'xem_danh_sach_chuc_vu',
            'xem_danh_sach_nhan_vien',
            'xem_danh_sach_cham_cong',
            'xem_danh_sach_nghi_phep',
            'xem_danh_sach_luat_hoa_hong',
            'xem_danh_sach_hoa_hong',
            'xem_danh_sach_tinh_luong',
            // Tạo / Cập nhật
            'tao_phong_ban',
            'cap_nhat_phong_ban',
            'tao_chuc_vu',
            'cap_nhat_chuc_vu',
            'tao_nhan_vien',
            'cap_nhat_nhan_vien',
            'xem_luong_nhan_vien',
            'cap_nhat_luong_nhan_vien',
            'tao_cham_cong',
            'cap_nhat_cham_cong',
            'tao_luat_hoa_hong',
            'cap_nhat_luat_hoa_hong',
            'tao_hoa_hong',
            'cap_nhat_hoa_hong',
            'duyet_hoa_hong',
            'tao_dot_tinh_luong',
            'tinh_luong',
            'xem_chi_tiet_phieu_luong',
            // Nghỉ phép
            'duyet_don_nghi_phep',
            'tu_choi_don_nghi_phep',
            'huy_don_nghi_phep',
        ]);

        // Kế toán lương — chỉ xem/sửa payroll, không quản lý nhân viên
        $payrollAccountant = Role::firstOrCreate(
            ['name' => 'Kế toán lương', 'guard_name' => 'web'],
        );
        $payrollAccountant->syncPermissions([
            'xem_danh_sach_nhan_vien',
            'xem_danh_sach_cham_cong',
            'xem_danh_sach_nghi_phep',
            'xem_danh_sach_luat_hoa_hong',
            'xem_danh_sach_hoa_hong',
            'xem_danh_sach_tinh_luong',
            'xem_chi_tiet_phieu_luong',
            'tao_dot_tinh_luong',
            'tinh_luong',
            'duyet_tinh_luong',
            'chi_tra_luong',
            'huy_dot_tinh_luong',
            'dieu_chinh_phieu_luong',
            'duyet_hoa_hong',
            'xem_luong_nhan_vien',
        ]);
    }
}