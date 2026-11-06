-- Generated from the current Laravel migrations using a fresh temporary SQLite database.

-- No application database or customer data is read or exported.

CREATE TABLE "audit_events" ("id" integer primary key autoincrement not null, "user_id" integer, "route_name" varchar, "method" varchar not null, "response_status" integer not null, "subject_type" varchar, "subject_id" varchar, "properties" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete set null);

CREATE TABLE "bank_accounts" ("id" integer primary key autoincrement not null, "bank_name" varchar not null, "account_number" varchar not null, "account_name" varchar not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "barcode_templates" ("id" integer primary key autoincrement not null, "name" varchar not null, "paper_type" varchar not null, "paper_width_mm" numeric not null, "paper_height_mm" numeric, "label_width_mm" numeric not null, "label_height_mm" numeric not null, "gap_horizontal_mm" numeric not null default '0', "gap_vertical_mm" numeric not null default '0', "margin_top_mm" numeric not null default '0', "margin_left_mm" numeric not null default '0', "columns" integer not null default '1', "rows" integer not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "cashier_shifts" ("id" integer primary key autoincrement not null, "user_id" integer not null, "starting_cash" numeric not null default '0', "ending_cash" numeric, "opened_at" datetime not null, "closed_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete restrict);

CREATE TABLE "categories" ("id" integer primary key autoincrement not null, "name" varchar not null, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "coupons" ("id" integer primary key autoincrement not null, "code" varchar not null, "type" varchar not null, "value" numeric not null, "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "customers" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar, "phone" varchar, "address" varchar, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "dining_tables" ("id" integer primary key autoincrement not null, "number" varchar not null, "capacity" integer not null default '2', "qr_token" varchar not null, "status" varchar not null default 'available', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "expense_categories" ("id" integer primary key autoincrement not null, "name" varchar not null, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "expense_payments" ("id" integer primary key autoincrement not null, "expense_id" integer not null, "paid_at" date not null, "amount" numeric not null, "payment_method" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("expense_id") references "expenses"("id") on delete cascade);

CREATE TABLE "expense_subcategories" ("id" integer primary key autoincrement not null, "expense_category_id" integer not null, "name" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("expense_category_id") references "expense_categories"("id") on delete restrict);

CREATE TABLE "expenses" ("id" integer primary key autoincrement not null, "expense_code" varchar not null, "reference_number" varchar, "expense_date" date not null, "expense_category_id" integer not null, "expense_subcategory_id" integer not null, "created_by" integer not null, "amount" numeric not null, "payment_status" varchar not null default 'unpaid', "description" text, "created_at" datetime, "updated_at" datetime, "is_non_cash" tinyint(1) not null default '0', foreign key("expense_category_id") references "expense_categories"("id") on delete restrict, foreign key("expense_subcategory_id") references "expense_subcategories"("id") on delete restrict, foreign key("created_by") references "users"("id") on delete restrict);

CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" text not null, "queue" text not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);

CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));

CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);

CREATE TABLE "loyalty_point_entries" ("id" integer primary key autoincrement not null, "customer_id" integer not null, "transaction_id" integer, "transaction_return_id" integer, "entry_type" varchar not null, "points" integer not null, "description" varchar, "created_at" datetime, "updated_at" datetime, foreign key("customer_id") references "customers"("id") on delete restrict, foreign key("transaction_id") references "transactions"("id") on delete restrict, foreign key("transaction_return_id") references "transaction_returns"("id") on delete restrict);

CREATE TABLE "material_stock_batches" ("id" integer primary key autoincrement not null, "material_id" integer not null, "batch_code" varchar not null, "expires_at" date, "created_at" datetime, "updated_at" datetime, "unit_cost" numeric, foreign key("material_id") references "materials"("id") on delete restrict);

CREATE TABLE "materials" ("id" integer primary key autoincrement not null, "unit_id" integer not null, "name" varchar not null, "minimum_quantity" numeric not null default ('0'), "created_at" datetime, "updated_at" datetime, "supplier_id" integer, foreign key("unit_id") references units("id") on delete restrict on update no action, foreign key("supplier_id") references "suppliers"("id") on delete set null);

CREATE TABLE "menu_bundle_items" ("id" integer primary key autoincrement not null, "bundle_menu_id" integer not null, "component_menu_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("bundle_menu_id") references "menus"("id") on delete cascade, foreign key("component_menu_id") references "menus"("id") on delete restrict);

CREATE TABLE "menu_recipes" ("id" integer primary key autoincrement not null, "menu_id" integer not null, "material_id" integer not null, "quantity" numeric not null, "created_at" datetime, "updated_at" datetime, foreign key("menu_id") references "menus"("id") on delete cascade, foreign key("material_id") references "materials"("id") on delete restrict);

CREATE TABLE "menu_variant_recipes" ("id" integer primary key autoincrement not null, "menu_variant_id" integer not null, "material_id" integer not null, "quantity" numeric not null, "created_at" datetime, "updated_at" datetime, foreign key("menu_variant_id") references "menu_variants"("id") on delete cascade, foreign key("material_id") references "materials"("id") on delete restrict);

CREATE TABLE "menu_variants" ("id" integer primary key autoincrement not null, "menu_id" integer not null, "name" varchar not null, "additional_price" numeric not null default '0', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("menu_id") references "menus"("id") on delete cascade);

CREATE TABLE "menus" ("id" integer primary key autoincrement not null, "category_id" integer not null, "name" varchar not null, "description" text, "selling_price" numeric not null, "is_available" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, "is_bundle" tinyint(1) not null default '0', "barcode" varchar, foreign key("category_id") references "categories"("id") on delete restrict);

CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);

CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

CREATE TABLE "product_promotion_customers" ("id" integer primary key autoincrement not null, "product_promotion_id" integer not null, "customer_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("product_promotion_id") references "product_promotions"("id") on delete cascade, foreign key("customer_id") references "customers"("id") on delete cascade);

CREATE TABLE "product_promotion_menus" ("id" integer primary key autoincrement not null, "product_promotion_id" integer not null, "menu_id" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("product_promotion_id") references "product_promotions"("id") on delete cascade, foreign key("menu_id") references "menus"("id") on delete cascade);

CREATE TABLE "product_promotions" ("id" integer primary key autoincrement not null, "name" varchar not null, "discount_type" varchar not null, "discount_value" numeric not null, "minimum_quantity" integer not null default '1', "all_menus" tinyint(1) not null default '1', "all_customers" tinyint(1) not null default '1', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "product_stock_batches" ("id" integer primary key autoincrement not null, "product_variant_id" integer not null, "batch_code" varchar not null, "expires_at" date, "unit_cost" numeric, "created_at" datetime, "updated_at" datetime, foreign key("product_variant_id") references "product_variants"("id") on delete restrict);

CREATE TABLE "product_stock_movements" ("id" integer primary key autoincrement not null, "product_stock_batch_id" integer not null, "transaction_id" integer, "type" varchar not null, "quantity" numeric not null, "unit_cost" numeric, "cost_amount" numeric, "description" varchar, "created_at" datetime, "updated_at" datetime, foreign key("product_stock_batch_id") references "product_stock_batches"("id") on delete restrict, foreign key("transaction_id") references "transactions"("id") on delete set null);

CREATE TABLE "product_variants" ("id" integer primary key autoincrement not null, "product_id" integer not null, "unit_id" integer not null, "label" varchar not null, "barcode" varchar, "selling_price" numeric not null, "minimum_quantity" numeric not null default '0', "is_available" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("product_id") references "products"("id") on delete restrict, foreign key("unit_id") references "units"("id") on delete restrict);

CREATE TABLE "products" ("id" integer primary key autoincrement not null, "sku" varchar not null, "category_id" integer not null, "name" varchar not null, "description" text, "is_available" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime, foreign key("category_id") references "categories"("id") on delete restrict);

CREATE TABLE "purchase_items" ("id" integer primary key autoincrement not null, "purchase_id" integer not null, "material_id" integer, "material_name" varchar not null, "unit_name" varchar not null, "quantity" numeric not null, "unit_cost" numeric not null, "line_total" numeric not null, "expires_at" date, "created_at" datetime, "updated_at" datetime, "product_variant_id" integer, "item_type" varchar not null default 'material', foreign key("material_id") references materials("id") on delete restrict on update no action, foreign key("purchase_id") references purchases("id") on delete cascade on update no action, foreign key("product_variant_id") references "product_variants"("id") on delete restrict);

CREATE TABLE "purchase_payments" ("id" integer primary key autoincrement not null, "purchase_id" integer not null, "paid_at" date not null, "amount" numeric not null, "payment_method" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("purchase_id") references "purchases"("id") on delete cascade);

CREATE TABLE "purchase_return_details" ("id" integer primary key autoincrement not null, "purchase_return_id" integer not null, "purchase_item_id" integer not null, "quantity" numeric not null, "amount" numeric not null default '0', "replacement_expires_at" date, "reason" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("purchase_return_id") references "purchase_returns"("id") on delete cascade, foreign key("purchase_item_id") references "purchase_items"("id") on delete restrict);

CREATE TABLE "purchase_returns" ("id" integer primary key autoincrement not null, "return_code" varchar not null, "purchase_id" integer not null, "created_by" integer not null, "return_date" date not null, "settlement_method" varchar not null, "status" varchar not null default 'pending', "amount" numeric not null default '0', "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("purchase_id") references "purchases"("id") on delete restrict, foreign key("created_by") references "users"("id") on delete restrict);

CREATE TABLE "purchases" ("id" integer primary key autoincrement not null, "purchase_code" varchar not null, "supplier_id" integer not null, "created_by" integer not null, "purchase_date" date not null, "status" varchar not null default 'pending', "payment_status" varchar not null default 'unpaid', "subtotal" numeric not null, "discount_type" varchar, "discount_value" numeric not null default '0', "discount" numeric not null default '0', "grand_total" numeric not null, "notes" text, "created_at" datetime, "updated_at" datetime, "purchase_type" varchar not null default 'materials', foreign key("supplier_id") references "suppliers"("id") on delete restrict, foreign key("created_by") references "users"("id") on delete restrict);

CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

CREATE TABLE "setup_state" ("id" integer not null, "is_complete" tinyint(1) not null default '0', primary key ("id"));

CREATE TABLE "stock_audit_task_items" ("id" integer primary key autoincrement not null, "stock_audit_task_id" integer not null, "material_id" integer, "material_name" varchar not null, "unit_name" varchar not null, "system_quantity" numeric not null, "shift1_quantity" numeric not null, "shift2_quantity" numeric, "shift1_note" varchar, "shift2_note" varchar, "created_at" datetime, "updated_at" datetime, foreign key("stock_audit_task_id") references "stock_audit_tasks"("id") on delete cascade, foreign key("material_id") references "materials"("id") on delete set null);

CREATE TABLE "stock_audit_tasks" ("id" integer primary key autoincrement not null, "task_code" varchar not null, "shift1_user_id" integer not null, "shift2_user_id" integer, "status" varchar not null default 'awaiting_shift2', "note" text, "shift1_completed_at" datetime not null, "completed_at" datetime, "created_at" datetime, "updated_at" datetime, foreign key("shift1_user_id") references "users"("id") on delete restrict, foreign key("shift2_user_id") references "users"("id") on delete restrict);

CREATE TABLE "stock_movements" ("id" integer primary key autoincrement not null, "material_stock_batch_id" integer not null, "transaction_id" integer, "type" varchar not null, "quantity" numeric not null, "description" varchar, "created_at" datetime, "updated_at" datetime, "unit_cost" numeric, "cost_amount" numeric, foreign key("material_stock_batch_id") references "material_stock_batches"("id") on delete restrict, foreign key("transaction_id") references "transactions"("id") on delete set null);

CREATE TABLE "stock_take_details" ("id" integer primary key autoincrement not null, "stock_take_id" integer not null, "material_id" integer, "material_name" varchar not null, "unit_name" varchar not null, "system_quantity" numeric not null, "counted_quantity" numeric not null, "difference" numeric not null, "note" text, "created_at" datetime, "updated_at" datetime, foreign key("stock_take_id") references "stock_takes"("id") on delete restrict, foreign key("material_id") references "materials"("id") on delete set null);

CREATE TABLE "stock_takes" ("id" integer primary key autoincrement not null, "reference" varchar not null, "user_id" integer not null, "checked_at" date not null, "note" text, "created_at" datetime, "updated_at" datetime, foreign key("user_id") references "users"("id") on delete restrict);

CREATE TABLE "store_charges" ("id" integer primary key autoincrement not null, "code" varchar not null, "name" varchar not null, "value" varchar not null, "is_active" tinyint(1) not null default '0', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "store_settings" ("id" integer primary key autoincrement not null, "key" varchar not null, "value" text, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "suppliers" ("id" integer primary key autoincrement not null, "code" varchar not null, "name" varchar not null, "phone" varchar, "email" varchar, "address" text, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "transaction_charges" ("id" integer primary key autoincrement not null, "transaction_id" integer not null, "code" varchar not null, "name" varchar not null, "amount" numeric not null, "created_at" datetime, "updated_at" datetime, "rate" varchar not null default '', foreign key("transaction_id") references "transactions"("id") on delete cascade);

CREATE TABLE "transaction_details" ("id" integer primary key autoincrement not null, "transaction_id" integer not null, "menu_id" integer, "item_name" varchar not null, "price" numeric not null, "quantity" integer not null, "line_total" numeric not null, "note" text, "created_at" datetime, "updated_at" datetime, "promotion_discount" numeric not null default ('0'), "promotion_name" varchar, "item_type" varchar not null default ('menu'), "product_variant_id" integer, "menu_variant_id" integer, foreign key("product_variant_id") references product_variants("id") on delete restrict on update no action, foreign key("transaction_id") references transactions("id") on delete cascade on update no action, foreign key("menu_id") references menus("id") on delete set null on update no action, foreign key("menu_variant_id") references "menu_variants"("id") on delete set null);

CREATE TABLE "transaction_recipe_details" ("id" integer primary key autoincrement not null, "transaction_detail_id" integer not null, "material_id" integer not null, "material_name" varchar not null, "unit_name" varchar not null, "quantity" numeric not null, "created_at" datetime, "updated_at" datetime, foreign key("transaction_detail_id") references "transaction_details"("id") on delete cascade, foreign key("material_id") references "materials"("id") on delete restrict);

CREATE TABLE "transaction_return_details" ("id" integer primary key autoincrement not null, "transaction_return_id" integer not null, "transaction_detail_id" integer not null, "quantity" integer not null, "refund_amount" numeric not null default '0', "reason" varchar not null, "created_at" datetime, "updated_at" datetime, foreign key("transaction_return_id") references "transaction_returns"("id") on delete cascade, foreign key("transaction_detail_id") references "transaction_details"("id") on delete restrict);

CREATE TABLE "transaction_returns" ("id" integer primary key autoincrement not null, "transaction_id" integer not null, "created_by" integer not null, "return_code" varchar not null, "return_date" date not null, "refund_method" varchar not null, "status" varchar not null default 'pending', "refund_amount" numeric not null default '0', "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("transaction_id") references "transactions"("id") on delete restrict, foreign key("created_by") references "users"("id") on delete restrict);

CREATE TABLE "transactions" ("id" integer primary key autoincrement not null, "invoice" varchar not null, "cashier_shift_id" integer not null, "customer_name" varchar not null, "customer_phone" varchar, "transaction_type" varchar not null default ('takeaway'), "status" varchar not null default ('pending'), "kitchen_status" varchar not null default ('pending'), "payment_method" varchar, "subtotal" numeric not null, "amount_paid" numeric not null default ('0'), "change_due" numeric not null default ('0'), "notes" text, "created_at" datetime, "updated_at" datetime, "customer_id" integer, "dining_table_id" integer, "coupon_id" integer, "coupon_code" varchar, "discount" numeric not null default ('0'), "grand_total" numeric not null default ('0'), "paid_at" datetime, "promotion_discount" numeric not null default ('0'), "coupon_discount" numeric not null default ('0'), "cogs_total" numeric, "cogs_complete" tinyint(1) not null default ('0'), "bank_account_id" integer, "loyalty_points_earned" integer not null default ('0'), "loyalty_points_redeemed" integer not null default ('0'), "loyalty_discount" numeric not null default ('0'), "transaction_kind" varchar not null default 'sale', "employee_id" integer, foreign key("bank_account_id") references bank_accounts("id") on delete restrict on update no action, foreign key("cashier_shift_id") references cashier_shifts("id") on delete restrict on update no action, foreign key("customer_id") references customers("id") on delete set null on update no action, foreign key("dining_table_id") references dining_tables("id") on delete set null on update no action, foreign key("coupon_id") references coupons("id") on delete set null on update no action, foreign key("employee_id") references "users"("id") on delete set null);

CREATE TABLE "units" ("id" integer primary key autoincrement not null, "name" varchar not null, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "role" varchar not null default 'manager', "is_active" tinyint(1) not null default '1');

CREATE INDEX "audit_events_created_at_user_id_index" on "audit_events" ("created_at", "user_id");

CREATE INDEX "audit_events_route_name_index" on "audit_events" ("route_name");

CREATE INDEX "audit_events_subject_type_subject_id_index" on "audit_events" ("subject_type", "subject_id");

CREATE INDEX "bank_accounts_bank_name_is_active_index" on "bank_accounts" ("bank_name", "is_active");

CREATE UNIQUE INDEX "barcode_templates_name_unique" on "barcode_templates" ("name");

CREATE INDEX "cache_expiration_index" on "cache" ("expiration");

CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");

CREATE INDEX "cashier_shifts_user_id_closed_at_index" on "cashier_shifts" ("user_id", "closed_at");

CREATE UNIQUE INDEX "categories_name_unique" on "categories" ("name");

CREATE UNIQUE INDEX "coupons_code_unique" on "coupons" ("code");

CREATE UNIQUE INDEX "customers_phone_unique" on "customers" ("phone");

CREATE UNIQUE INDEX "dining_tables_number_unique" on "dining_tables" ("number");

CREATE UNIQUE INDEX "dining_tables_qr_token_unique" on "dining_tables" ("qr_token");

CREATE UNIQUE INDEX "expense_categories_name_unique" on "expense_categories" ("name");

CREATE INDEX "expense_payments_expense_id_paid_at_index" on "expense_payments" ("expense_id", "paid_at");

CREATE UNIQUE INDEX "expense_subcategories_expense_category_id_name_unique" on "expense_subcategories" ("expense_category_id", "name");

CREATE INDEX "expenses_expense_category_id_expense_date_index" on "expenses" ("expense_category_id", "expense_date");

CREATE UNIQUE INDEX "expenses_expense_code_unique" on "expenses" ("expense_code");

CREATE INDEX "expenses_expense_date_payment_status_index" on "expenses" ("expense_date", "payment_status");

CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");

CREATE INDEX "jobs_queue_index" on "jobs" ("queue");

CREATE INDEX "loyalty_point_entries_customer_id_created_at_index" on "loyalty_point_entries" ("customer_id", "created_at");

CREATE INDEX "loyalty_point_entries_transaction_id_entry_type_index" on "loyalty_point_entries" ("transaction_id", "entry_type");

CREATE UNIQUE INDEX "loyalty_point_entries_transaction_return_id_entry_type_unique" on "loyalty_point_entries" ("transaction_return_id", "entry_type");

CREATE INDEX "material_stock_batches_material_id_expires_at_index" on "material_stock_batches" ("material_id", "expires_at");

CREATE UNIQUE INDEX "materials_name_unique" on "materials" ("name");

CREATE UNIQUE INDEX "menu_bundle_items_bundle_menu_id_component_menu_id_unique" on "menu_bundle_items" ("bundle_menu_id", "component_menu_id");

CREATE UNIQUE INDEX "menu_recipes_menu_id_material_id_unique" on "menu_recipes" ("menu_id", "material_id");

CREATE UNIQUE INDEX "menu_variant_recipes_menu_variant_id_material_id_unique" on "menu_variant_recipes" ("menu_variant_id", "material_id");

CREATE UNIQUE INDEX "menu_variants_menu_id_name_unique" on "menu_variants" ("menu_id", "name");

CREATE UNIQUE INDEX "menus_barcode_unique" on "menus" ("barcode");

CREATE INDEX "menus_is_available_category_id_index" on "menus" ("is_available", "category_id");

CREATE INDEX "menus_is_bundle_index" on "menus" ("is_bundle");

CREATE UNIQUE INDEX "product_promotion_customers_product_promotion_id_customer_id_unique" on "product_promotion_customers" ("product_promotion_id", "customer_id");

CREATE UNIQUE INDEX "product_promotion_menus_product_promotion_id_menu_id_unique" on "product_promotion_menus" ("product_promotion_id", "menu_id");

CREATE INDEX "product_promotions_is_active_index" on "product_promotions" ("is_active");

CREATE UNIQUE INDEX "product_stock_batches_product_variant_id_batch_code_unique" on "product_stock_batches" ("product_variant_id", "batch_code");

CREATE INDEX "product_stock_batches_product_variant_id_expires_at_index" on "product_stock_batches" ("product_variant_id", "expires_at");

CREATE INDEX "product_stock_movements_product_stock_batch_id_type_index" on "product_stock_movements" ("product_stock_batch_id", "type");

CREATE UNIQUE INDEX "product_variants_barcode_unique" on "product_variants" ("barcode");

CREATE INDEX "product_variants_is_available_product_id_index" on "product_variants" ("is_available", "product_id");

CREATE UNIQUE INDEX "product_variants_product_id_label_unique" on "product_variants" ("product_id", "label");

CREATE INDEX "products_is_available_category_id_index" on "products" ("is_available", "category_id");

CREATE UNIQUE INDEX "products_sku_unique" on "products" ("sku");

CREATE INDEX "purchase_items_purchase_id_material_id_index" on "purchase_items" ("purchase_id", "material_id");

CREATE INDEX "purchase_payments_purchase_id_paid_at_index" on "purchase_payments" ("purchase_id", "paid_at");

CREATE INDEX "purchase_return_details_purchase_item_id_purchase_return_id_index" on "purchase_return_details" ("purchase_item_id", "purchase_return_id");

CREATE INDEX "purchase_returns_purchase_id_status_index" on "purchase_returns" ("purchase_id", "status");

CREATE UNIQUE INDEX "purchase_returns_return_code_unique" on "purchase_returns" ("return_code");

CREATE INDEX "purchase_returns_status_return_date_index" on "purchase_returns" ("status", "return_date");

CREATE INDEX "purchases_payment_status_purchase_date_index" on "purchases" ("payment_status", "purchase_date");

CREATE UNIQUE INDEX "purchases_purchase_code_unique" on "purchases" ("purchase_code");

CREATE INDEX "purchases_status_purchase_date_index" on "purchases" ("status", "purchase_date");

CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");

CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");

CREATE UNIQUE INDEX "stock_audit_task_items_stock_audit_task_id_material_id_unique" on "stock_audit_task_items" ("stock_audit_task_id", "material_id");

CREATE INDEX "stock_audit_tasks_status_created_at_index" on "stock_audit_tasks" ("status", "created_at");

CREATE UNIQUE INDEX "stock_audit_tasks_task_code_unique" on "stock_audit_tasks" ("task_code");

CREATE INDEX "stock_movements_material_stock_batch_id_type_index" on "stock_movements" ("material_stock_batch_id", "type");

CREATE INDEX "stock_take_details_stock_take_id_material_id_index" on "stock_take_details" ("stock_take_id", "material_id");

CREATE INDEX "stock_takes_checked_at_index" on "stock_takes" ("checked_at");

CREATE UNIQUE INDEX "stock_takes_reference_unique" on "stock_takes" ("reference");

CREATE INDEX "store_charges_code_is_active_index" on "store_charges" ("code", "is_active");

CREATE UNIQUE INDEX "store_settings_key_unique" on "store_settings" ("key");

CREATE UNIQUE INDEX "suppliers_code_unique" on "suppliers" ("code");

CREATE INDEX "suppliers_name_index" on "suppliers" ("name");

CREATE INDEX "transaction_charges_transaction_id_code_index" on "transaction_charges" ("transaction_id", "code");

CREATE INDEX "transaction_details_item_type_product_variant_id_index" on "transaction_details" ("item_type", "product_variant_id");

CREATE UNIQUE INDEX "transaction_recipe_details_transaction_detail_id_material_id_unique" on "transaction_recipe_details" ("transaction_detail_id", "material_id");

CREATE INDEX "transaction_return_details_transaction_detail_id_index" on "transaction_return_details" ("transaction_detail_id");

CREATE UNIQUE INDEX "transaction_returns_return_code_unique" on "transaction_returns" ("return_code");

CREATE INDEX "transaction_returns_return_date_status_refund_method_index" on "transaction_returns" ("return_date", "status", "refund_method");

CREATE INDEX "transaction_returns_transaction_id_status_index" on "transaction_returns" ("transaction_id", "status");

CREATE INDEX "transactions_customer_id_created_at_index" on "transactions" ("customer_id", "created_at");

CREATE UNIQUE INDEX "transactions_invoice_unique" on "transactions" ("invoice");

CREATE INDEX "transactions_kitchen_status_created_at_index" on "transactions" ("kitchen_status", "created_at");

CREATE INDEX "transactions_paid_at_index" on "transactions" ("paid_at");

CREATE INDEX "transactions_status_created_at_index" on "transactions" ("status", "created_at");

CREATE INDEX "transactions_transaction_kind_status_created_at_index" on "transactions" ("transaction_kind", "status", "created_at");

CREATE UNIQUE INDEX "units_name_unique" on "units" ("name");

CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

CREATE INDEX "users_is_active_index" on "users" ("is_active");

CREATE INDEX "users_role_index" on "users" ("role");

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('1', '0001_01_01_000000_create_users_table', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('2', '0001_01_01_000001_create_cache_table', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('3', '0001_01_01_000002_create_jobs_table', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('4', '2026_11_03_000001_create_setup_state_table', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('5', '2026_11_03_000002_create_catalog_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('6', '2026_11_03_000003_create_pos_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('7', '2026_11_03_000004_create_recipe_inventory_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('8', '2026_11_03_000005_create_tables_customers_coupons', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('9', '2026_11_03_000006_create_supplier_purchase_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('10', '2026_11_03_000007_create_expense_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('11', '2026_11_03_000008_create_transaction_return_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('12', '2026_11_03_000009_create_purchase_return_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('13', '2026_11_03_000010_add_paid_at_to_transactions_table', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('14', '2026_11_03_000011_add_role_and_active_status_to_users', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('15', '2026_11_03_000012_create_audit_events_table', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('16', '2026_11_03_000013_create_product_promotion_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('17', '2026_11_03_000014_create_menu_bundle_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('18', '2026_11_03_000015_add_barcode_to_menus', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('19', '2026_11_03_000016_create_store_settings_and_barcode_templates', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('20', '2026_11_03_000017_create_stock_take_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('21', '2026_11_03_000018_add_batch_cost_snapshots', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('22', '2026_11_03_000019_create_transaction_charge_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('23', '2026_11_03_000020_add_rate_snapshot_to_transaction_charges', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('24', '2026_11_03_000021_create_bank_accounts_table', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('25', '2026_11_03_000022_create_retail_product_inventory_tables', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('26', '2026_11_03_000023_add_retail_products_to_transactions', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('27', '2026_11_03_000024_add_product_purchases', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('28', '2026_11_03_000025_create_loyalty_points', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('29', '2026_11_03_000026_add_supplier_to_materials', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('30', '2026_11_06_000001_create_menu_variants_and_employee_consumption', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('31', '2026_11_06_000002_create_stock_audit_tasks', '1');

INSERT INTO "migrations" ("id", "migration", "batch") VALUES ('32', '2026_11_06_000003_add_non_cash_expenses', '1');

INSERT INTO "setup_state" ("id", "is_complete") VALUES ('1', '0');
