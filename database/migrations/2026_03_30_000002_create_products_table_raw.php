<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            return;
        }
        DB::statement("CREATE TABLE products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            banner_image VARCHAR(255) NOT NULL,
            product_images JSON NULL,
            category_id BIGINT UNSIGNED NOT NULL,
            status ENUM('publish', 'unpublish', 'pending', 'trash') NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_description TEXT NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            sale_price DECIMAL(10, 2) NULL,
            discount_type VARCHAR(255) NULL,
            discount_value DECIMAL(10, 2) NULL,
            tax_status VARCHAR(255) NOT NULL,
            installation_available BOOLEAN NULL,
            installation_price DECIMAL(10, 2) NULL,
            installation_details TEXT NULL,
            weight DECIMAL(10, 2) NULL,
            height DECIMAL(10, 2) NULL,
            width DECIMAL(10, 2) NULL,
            length DECIMAL(10, 2) NULL,
            total_stock INT NOT NULL,
            limited_stock INT NULL,
            sku VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS products;");
    }
};
