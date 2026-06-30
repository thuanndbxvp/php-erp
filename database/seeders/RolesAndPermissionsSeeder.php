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
        ]);
    }
}