<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Seed các bảng cơ bản không phụ thuộc
            CategorySeeder::class,        // Categories
            ProductUnitSeeder::class,     // Product Units
            SupplierSeeder::class,        // Suppliers
            UserSeeder::class,            // Users & Roles

            // 2. Seed các bảng sản phẩm
            ProductSeeder::class,         // Products (phụ thuộc Category, ProductUnit)
            ProductSupplierSeeder::class, // Product-Supplier relationships

            // 3. Seed các bảng đơn hàng mua
            PurchaseOrderSeeder::class,   // Purchase Orders
            PurchaseOrderItemSeeder::class, // Purchase Order Items

            // 4. Seed các bảng nhận hàng
            BatchSeeder::class,           // Batches (phụ thuộc PurchaseOrder)
            BatchItemSeeder::class,       // Batch Items (phụ thuộc Batch, Product, PurchaseOrderItem)

            // 5. Seed các bảng trả hàng
            PurchaseReturnSeeder::class,  // Purchase Returns (phụ thuộc PurchaseOrder)
            PurchaseReturnItemSeeder::class, // Purchase Return Items

            // 6. Seed các bảng bán hàng
            CustomerSeeder::class,        // Customers

            BillSeeder::class,
            BillDetailSeeder::class,

            InventoryTransactionSeeder::class
        ]);
    }
}
