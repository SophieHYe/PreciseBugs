<?php


// Product Details for purchase
if(isset($_GET['page']) and $_GET['page'] == "productDetails") {

    $product_id = "";
    if(isset($_GET["product_id"])) {
        $selectProductId = easySelecta(array(
            "table"     => "products",
            "fields"    => "product_id",
            "where"     => array(
                "product_id" => $_GET["product_id"],
                " or product_code" => $_GET["product_id"],
            )
        ));

        if($selectProductId !== false) {
            $product_id = $selectProductId["data"][0]["product_id"];
        }
    }

    // If no product id
    if(empty($product_id)) {
        die("Sorry no product found!");
    }

    //$this_product = product_type($product_id);
    $select_product = easySelectA(array(
        "table"     => "products",
        "fields"    => "product_id, concat(product_name, ' ', if(product_group is null, '', left(product_group, 3))) as product_name, product_type, 0 as product_discount, 
                        round(product_sale_price, 4) as product_sale_price, round(product_purchase_price, 4) as product_purchase_price, product_unit, has_expiry_date",
        "where"     => array(
            "is_trash = 0 and product_id"   => $product_id
        )
    ));

    // If there is no product againts given ID
    if(!$select_product) {
        die("Sorry no product found!");
    }

    // This variable will be kept all data for returning
    $returnData = array();
    $product = $select_product["data"][0];

    // Use for checking product type
    $product_is = function($type) {
        return $type === $GLOBALS["product"]["product_type"];
    };


    if( $product_is("Variable")) { 


        $select_variation = easySelectA(array(
            "table"     => "products as parent_product",
            "fields"    => "product_meta.product_id as id, 
                            (case when meta_type = 'Variation' then 'V' 
                                when meta_type = 'Default-Variation' then 'DV' 
                            END) as t,  
                            meta_key as mk, meta_value as mv",
            "join"      => array(
                "left join {$table_prefix}products as child_products on parent_product.product_id = child_products.product_parent_id",
                "inner join ( select 
                        product_id,
                        meta_type,
                        meta_key, 
                        meta_value 
                    from {$table_prefix}product_meta 
                ) as product_meta on product_meta.product_id = parent_product.product_id or product_meta.product_id = child_products.product_id"
            ),
            "where"     => array(
                "child_products.is_trash = 0 and parent_product.product_id" => $product_id
            ),
            "groupby"   => "product_meta.product_id, meta_type, meta_key, meta_value"
        
        ))["data"];

        // Store the product data
        array_push($returnData, array(
            "pid"  => $product["product_id"], // pid = Product id
            "pn"   => $product["product_name"], // pn = product Name
            "pv"  => $select_variation
        ));


    } else if( $product_is("Grouped") ) {

        $selectGroupedProducts = easySelectA(array(
            "table"     => "bg_product_items as bg_product_items",
            "fields"    => "bg_item_product_id, product_name, round(product_purchase_price, 2) as product_purchase_price, product_unit, bg_product_qnt, 0 as product_discount",
            "join"      => array(
                "left join {$table_prefix}products as products on products.product_id = bg_item_product_id"
            ),
            "where"     => array(
                "is_raw_materials = 0 and bg_product_id" => $product_id
            )
        ));

        if($selectGroupedProducts !== false) {

            foreach($selectGroupedProducts["data"] as $pkey => $pvalue) {

                // Store the product data
                array_push($returnData, 
                    array(
                        "pid"  => $pvalue["bg_item_product_id"], // pid = Product id
                        "pn"   => $pvalue["product_name"], // pn = product Name
                        "pd"   => $pvalue["product_discount"], // pd = product Discount 
                        "iq"   => $pvalue["bg_product_qnt"], // iq = Item Qunatity
                        "pu"   => $pvalue["product_unit"], // iq = Item Qunatity
                        "pp"   => $pvalue["product_purchase_price"], // sp = purchase price
                    )
                );

            }

        }
        
    } else {

        // For normal product
        array_push($returnData, array(
            "pid"  => $product["product_id"], // pid = Product id
            "pn"   => $product["product_name"], // pn = product Name
            "pd"   => $product["product_discount"], // pd = product Discount
            "sp"   => $product["product_sale_price"], // sp = sale price
            "pp"   => $product["product_purchase_price"], // sp = purchase price
            "pu"   => $product["product_unit"], // pu = Product Unit
            "hed"  => $product["has_expiry_date"] // hed = Has Expiry Date
        ));

    }

    echo json_encode($returnData);

}


// Product details for POS
if(isset($_GET['page']) and $_GET['page'] == "productDetailsForPos") {

    $product_id = "";
    if(isset($_GET["product_id"])) {
        $selectProductId = easySelecta(array(
            "table"     => "products",
            "fields"    => "product_id",
            "where"     => array(
                "product_id" => $_GET["product_id"],
                " or product_code" => $_GET["product_id"],
            )
        ));

        if($selectProductId !== false) {
            $product_id = $selectProductId["data"][0]["product_id"];
        }
    }

    // If no product id
    if(empty($product_id)) {
        
        $returnData = array (
            "error" => true,
            "msg"   => "Product out of stock or not found."
        );

        echo json_encode($returnData);
        exit();
    }

    $warehouse_id = isset($_GET["warehouse_id"]) ? (int)safe_input($_GET["warehouse_id"]) : "";
    $customer_id = isset($_GET["cid"]) ? (int)safe_input($_GET["cid"]) : "";
    $product_qnt = (isset($_GET["pqnt"]) and !empty($_GET["pqnt"])) ? safe_entities($_GET["pqnt"]) : get_options("defaultSaleQnt");
    $packet = ( isset($_GET["packet"]) and !empty($_GET["packet"]) ) ? safe_entities($_GET["packet"]) : 0;

    $customerType = "consumer";
    $selectCustomerType = easySelectA(array(
        "table"     => "customers",
        "fields"    => "customer_type",
        "where"     => array(
            "customer_id"   => $customer_id
        )
    ));

    if($selectCustomerType !== false) {
        $customerType = strtolower($selectCustomerType["data"][0]["customer_type"]);
    }

    // Settings
    $allowToAddStockOutProduct = get_options("allowToAddStockOutProductInPOS");
    $allowToSaleStockOutProduct = get_options("allowToSaleStockOutProductInPOS");

    $returnData = array();

    $select_product = easySelectA(array(
        "table"     => "products as product",
        "fields"    => "product.product_id as product_id, concat(product_name, ' ', if(product_group is null, '', left(product_group, 3))) as product_name, product_generic, 
                        if(stock_in is null, 0, round(stock_in, 2) ) as stock_in, product_type, product_{$customerType}_discount as product_discount, round(product_sale_price, 2) as product_sale_price, 
                        round(product_purchase_price, 2) as product_purchase_price, product_packet_qnt, product_unit, maintain_stock, has_expiry_date, has_batch",
        "join"      => array(
            "left join ( select 
                            vp_id,
                            if(batch_id is not null, 1, 0) as has_batch, /** Checking the product if set has expriy date and there also have batch entry*/
                            warehouse, 
                            sum(base_stock_in/base_qty) as stock_in 
                        FROM product_base_stock
                        where vp_id= '{$product_id}' and ( batch_id is null or date(batch_expiry_date) > curdate() ) and warehouse = '{$warehouse_id}' 
                        group by vp_id
                    ) as stock on stock.vp_id =  product.product_id"
        ),
        "where"     => array(
            "product.is_trash = 0 and product.product_id"   => $product_id
        )
    ));


    // If there is no product againts given ID
    if(!$select_product) {

        $returnData = array (
            "error" => true,
            "msg"   => "Product out of stock or not found."
        );

        echo json_encode($returnData);
        exit();

    }

    // This variable will be kept all data for returning
    $returnData = array();
    $product = $select_product["data"][0];

    // Use for checking product type
    $product_is = function($type) {
        return $type === $GLOBALS["product"]["product_type"];
    };


    if( $product_is("Variable")) {

        $select_variation = easySelectA(array(
            "table"     => "products as parent_product",
            "fields"    => "product_meta.product_id as id, 
                            (case when meta_type = 'Variation' then 'V' 
                                when meta_type = 'Default-Variation' then 'DV' 
                            END) as t,  
                            meta_key as mk, meta_value as mv",
            "join"      => array(
                "left join {$table_prefix}products as child_products on parent_product.product_id = child_products.product_parent_id",
                "inner join ( select 
                        product_id,
                        meta_type,
                        meta_key, 
                        meta_value 
                    from {$table_prefix}product_meta 
                ) as product_meta on product_meta.product_id = parent_product.product_id or product_meta.product_id = child_products.product_id"
            ),
            "where"     => array(
                "child_products.is_trash = 0 and parent_product.product_id" => $product_id
            ),
            "groupby"   => "product_meta.product_id, meta_type, meta_key, meta_value"
        
        ))["data"];

        // Store the product data
        array_push($returnData, array(
            "pid"  => $product["product_id"], // pid = Product id
            "pn"   => $product["product_name"], // pn = product Name
            "pv"  => $select_variation
        ));
        
    } else {

        // Select products, which have sub/bundle products and check it if there have enough quantity in stock
        
        $subProductsStockCheck = easySelectA(array(
            "table"     => "bg_product_items",
            "fields"    => "bg_item_product_id, product_name, product_{$customerType}_discount as product_discount, 
                            bg_item_product_id, 
                            round(sub_product.product_sale_price, 2) as product_sale_price,
                            round(sub_product.product_purchase_price, 2) as product_purchase_price,
                            sub_product.product_generic as product_generic,
                            sub_product.product_unit as product_unit,
                            sub_product.product_packet_qnt as product_packet_qnt,
                            sub_product.has_expiry_date as has_expiry_date,
                            sub_product.maintain_stock as maintain_stock,
                            if(stock_in is null, 0, round(stock_in, 2)) as stock_in,
                            round(bg_product_qnt, 2) as bg_product_qnt,
                            has_batch
                            ",
            "join"      => array(
                "left join {$table_prefix}products as sub_product on sub_product.product_id = bg_item_product_id",
                "left join ( select 
                                vp_id,
                                if(batch_id is not null, 1, 0) as has_batch, /** Checking the product if set has expriy date and there also have batch entry*/
                                sum(base_stock_in/base_qty) as stock_in 
                        FROM product_base_stock
                        where ( batch_id is null or date(batch_expiry_date) > curdate() ) and warehouse = '{$warehouse_id}' 
                        group by vp_id
                    ) as stock on stock.vp_id = bg_item_product_id"
            ),
            "where"     => array(
                "is_raw_materials = 0 and bg_product_id = {$product_id}"
            )

        ));


        // default false value for stock out product in bundle/ sub product
        $subProductIsStockOut = false;

        // Checking sub product stock
        if($subProductsStockCheck !== false) {

            // Set stock quantity to null by default
            $lowerQuantity = null;

            foreach($subProductsStockCheck["data"] as $subKey => $subProduct) {

                // Calculate stock quantity
                $stockQnt = $subProduct["stock_in"] / $subProduct["bg_product_qnt"];

                // Check there at least one stock out product in this bundle product
                $subProductIsStockOut = $subProductIsStockOut ?: $product_qnt > $stockQnt;

                 // Check if the $product_qnt/ Sale quantity is below of stock quantity
                // And return the lower quantity with error msg
                if( $subProduct["maintain_stock"] == 1 and $product_qnt > $stockQnt and ( !$allowToAddStockOutProduct and !$allowToSaleStockOutProduct ) and ( $lowerQuantity === null or $lowerQuantity > $stockQnt )) {
                    
                    $product_unit = $subProduct["product_unit"] !== null ? $subProduct["product_unit"] . "(s)" : "";

                    $returnData = array (
                        "error" => true,
                        "stq"   => $stockQnt,
                        "msg"   => "The Bundle/ Grouped item ({$subProduct["product_name"]}) only left {$subProduct["stock_in"]}/{$subProduct["bg_product_qnt"]} = {$stockQnt} {$product_unit} in stock. But it is defined {$product_qnt} {$product_unit}. Quantity must be bellow or equal to product stock in."
                    );

                    $lowerQuantity = $stockQnt;

                } else if( $product_is("Grouped") ) { 
                    
                    // If there is no error, add the grouped product in $returnData
                    // Store the Grouped product data
                    array_push($returnData, array(
                        "pid"  => $subProduct["bg_item_product_id"], // pid = Product id
                        "pn"   => $subProduct["product_name"], // pn = product Name
                        "pd"   => $subProduct["product_discount"], // pd = product Discount 
                        "stq"  => $stockQnt, // stq = Stock Quantity
                        "iq"   => $subProduct["bg_product_qnt"], // item quantity
                        "so"   => ( $subProduct["maintain_stock"] == 1 and ( $product_qnt > $stockQnt or $subProductIsStockOut ) and !$allowToSaleStockOutProduct) ? 1 : 0, // Stock Out
                        "sp"   => $subProduct["product_sale_price"], // sp = sale price
                        "pp"   => $subProduct["product_purchase_price"], // pp = purchase price
                        "gn"   => $subProduct["product_generic"], // gn = generic name
                        "pu"   => $subProduct["product_unit"], // pu = Product Unit
                        "pq"   => $subProduct["product_packet_qnt"], // pu = Product Unit
                        "hed"  => ($subProduct["has_expiry_date"] and $subProduct["has_batch"]) ? 1 : 0, // hed = Has Expiry Date
                    ));

                }

            }

        }

        // Check there are any error in grouped/ bundle product checking
        // and If the product is not grouped product
        if( !isset($returnData["error"]) and $product_is("Grouped") === false ) {

            if( $product["maintain_stock"] == 1 and  $product["stock_in"] == 0 and ( !$allowToAddStockOutProduct and !$allowToSaleStockOutProduct ) ) {  // Check if there enough stock for normal product

                $returnData = array (
                    "error" => true,
                    "msg"   => "Product out of stock in the selected warehouse."
                );
    
                
            } else if( $product["maintain_stock"] == 1 and $product_qnt > $product["stock_in"] and ( !$allowToAddStockOutProduct and !$allowToSaleStockOutProduct )  ) {
                
                $product_unit = $product["product_unit"] !== null ? $product["product_unit"] . "(s)" : "";
    
                $returnData = array (
                    "error" => true,
                    "msg"   => "Only {$product["stock_in"]} {$product_unit} left in stock. You entered total {$product_qnt} {$product_unit}. Quantity must be bellow or equal to product stock in.",
                    "stq" => $product["stock_in"],
                    "sp" => $product["product_sale_price"],
                    "pp" => $product["product_purchase_price"]
                );
    
            } else {
    
                // Store the product data
                // Normal and Bundle Product
                array_push($returnData, array(
                    "pid"  => $product["product_id"], // pid = Product id
                    "pn"   => $product["product_name"], // pn = product Name
                    "pd"   => $product["product_discount"], // pd = product Discount 
                    "stq"  => $product["stock_in"], // stq = Stock Quantity
                    "iq"   => $product_qnt, // item quantity
                    "so"   => ( $product["maintain_stock"] == 1 and ( $product_qnt > $product["stock_in"] or $subProductIsStockOut ) and !$allowToSaleStockOutProduct) ? 1 : 0, // SO = stock out
                    "sp"   => $product["product_sale_price"], // sp = sale price
                    "pp"   => $product["product_purchase_price"], // pp = purchase price
                    "gn"   => $product["product_generic"], // gn = generic name
                    "pu"   => $product["product_unit"], // pu = Product Unit
                    "pq"   => $product["product_packet_qnt"], // pu = Product Unit
                    "hed"  => ($product["has_expiry_date"] and $product["has_batch"]) ? 1 : 0, // hed = Has Expiry Date
                ));

            }

        }

    }

    echo json_encode($returnData);

}



// Product for return
if(isset($_GET['page']) and $_GET['page'] == "productDetailsForReturn") {

    $product_id = "";
    if(isset($_GET["product_id"])) {
        $selectProductId = easySelecta(array(
            "table"     => "products",
            "fields"    => "product_id",
            "where"     => array(
                "product_id" => $_GET["product_id"],
                " or product_code" => $_GET["product_id"],
            )
        ));

        if($selectProductId !== false) {
            $product_id = $selectProductId["data"][0]["product_id"];
        }
    }

    // If no product id
    if(empty($product_id)) {
        die("Sorry no product found!");
    }


    $customer_id = isset($_GET["customer_id"]) ? safe_input($_GET["customer_id"]) : "";
    $customerType = "consumer";
    $selectCustomerType = easySelectA(array(
        "table"     => "customers",
        "fields"    => "customer_type",
        "where"     => array(
            "customer_id"   => $customer_id
        )
    ));

    if($selectCustomerType !== false) {
        $customerType = strtolower($selectCustomerType["data"][0]["customer_type"]);
    }

    $select_product = easySelectA(array(
        "table"     => "products",
        "fields"    => "product_id, product_name, product_type, product_{$customerType}_discount as product_discount, round(product_sale_price, 2) as product_sale_price, round(product_purchase_price, 2) as product_purchase_price, product_unit, has_expiry_date",
        "where"     => array(
            "is_trash = 0 and product_id"   => $product_id
        )
    ));

    // If there is no product againts given ID
    if(!$select_product) {
        die("Sorry no product found!");
    }

    // This variable will be kept all data for returning
    $returnData = array();
    $product = $select_product["data"][0];

    // Use for checking product type
    $product_is = function($type) {
        return $type === $GLOBALS["product"]["product_type"];
    };


    if( $product_is("Variable")) { 

        $select_variation = easySelectA(array(
            "table"     => "products as parent_product",
            "fields"    => "product_meta.product_id as id, 
                            (case when meta_type = 'Variation' then 'V' 
                                when meta_type = 'Default-Variation' then 'DV' 
                            END) as t,  
                            meta_key as mk, meta_value as mv",
            "join"      => array(
                "left join {$table_prefix}products as child_products on parent_product.product_id = child_products.product_parent_id",
                "inner join ( select 
                        product_id,
                        meta_type,
                        meta_key, 
                        meta_value 
                    from {$table_prefix}product_meta 
                ) as product_meta on product_meta.product_id = parent_product.product_id or product_meta.product_id = child_products.product_id"
            ),
            "where"     => array(
                "parent_product.product_id" => $product_id
            ),
            "groupby"   => "product_meta.product_id, meta_type, meta_key, meta_value"
        
        ))["data"];

        // Store the product data
        array_push($returnData, array(
            "pid"  => $product["product_id"], // pid = Product id
            "pn"   => $product["product_name"], // pn = product Name
            "pv"  => $select_variation
        ));
        
        
    } else {


        // For normal product
        $normal_product = easySelectA(array(
            "table"     => "products as products",
            "fields"    => "
                    product_id, product_name, product_{$customerType}_discount as product_discount, product_unit, has_expiry_date, round(product_sale_price, 2) as product_sale_price,
                    if(sale_item_quantity is null, 0, round(sale_item_quantity, 2)) as purchasedQnt,
                    if(returns_products_quantity is null, 0, round(returns_products_quantity, 2)) as returnedQnt
            ",
            "join"      => array(
                "left join (
                    select
                        stock_product_id,
                        sum(case when stock_type = 'sale' then stock_item_qty end ) as sale_item_quantity,
                        sum(case when stock_type = 'sale-return' then stock_item_qty end ) as returns_products_quantity
                    from {$table_prefix}product_stock as product_stock
                    left join {$table_prefix}sales on stock_sales_id = sales_id
                    where stock_product_id = {$product_id} and sales_customer_id = {$customer_id} and product_stock.is_trash = 0
                    group by stock_product_id
                ) as stock on stock_product_id = product_id",
            ),
            "where" => array(
                "product_id"    => $product_id
            )
        ))["data"][0];
    
        array_push($returnData, array(
            "pid"  => $normal_product["product_id"], // pid = Product id
            "pn"   => $normal_product["product_name"], // pn = product Name
            "pd"   => $normal_product["product_discount"], // pd = product Discount 
            "prq"  => $normal_product["purchasedQnt"], // prq = Purchased Quantity
            "rtq"  => $normal_product["returnedQnt"], // rtq = Returned Quantity
            "sp"   => $normal_product["product_sale_price"], // sp = sale price
            "pu"    => $normal_product["product_unit"], // pu = product_unit
            "hed"    => $normal_product["has_expiry_date"]
        ));

    }

    echo json_encode($returnData);

}


// Product List
if(isset($_GET['page']) and $_GET['page'] == "productList") {

    $productCategoryFilter = ( isset($_GET["catId"]) and !empty($_GET["catId"]) ) ? $_GET["catId"] : "";
    $productBrandFilter = ( isset($_GET["brand"]) and !empty($_GET["brand"]) ) ? $_GET["brand"] : "";
    $productGenericFilter = ( isset($_GET["generic"]) and !empty($_GET["generic"]) ) ? $_GET["generic"] : "";
    $productAuthorFilter = ( isset($_GET["author"]) and !empty($_GET["author"]) ) ? $_GET["author"] : "";

    // If there are any edition to filter, we do not need product_type != 'Child' filter
    $productEditionFilter = ( isset($_GET["edition"]) and !empty($_GET["edition"]) ) ? " product_edition = '{$_GET["edition"]}' " : " product_type != 'Child' ";

    $oderBy = array();
    
    if( !empty(get_options("defaultProductOrder")) ) {

        $oderBy = array(
            get_options("defaultProductOrder") => empty(get_options("defaultProductOrderBy")) ? "ASC" : get_options("defaultProductOrderBy")
        );

    }
    
    $ProductList = easySelectA(array(
        "table"     => "products",
        "fields"    => "product_id as id, product_name as name, length(product_photo) as v",
        "where"     => array (
            "is_trash = 0 AND {$productEditionFilter}",
            " AND product_category_id"          => $productCategoryFilter,
            " AND product_brand_id"             => $productBrandFilter,
            " AND product_generic"              => $productGenericFilter
        ),
        "join"  => array(
            "left join ( select 
                    stock_product_id, 
                    if(stock_item_qty is null, 0, sum(stock_item_qty)) as totalSoldQnt 
                from {$table_prefix}product_stock 
                where is_trash = 0 and stock_type = 'sale' 
                group by stock_product_id 
            ) as sale_items on stock_product_id = product_id"
        ),
        "orderby"   => $oderBy,
        "limit"     => array (
            "start"     => 0,
            "length"    => empty(get_options("maxProductDisplay")) ? 1500 : get_options("maxProductDisplay")
        )
        
    ));
    
    if($ProductList) {
        
        echo json_encode($ProductList["data"]);

    } else {
        echo "null";
    }

}


// Product Unit Details
if(isset($_GET['page']) and $_GET['page'] == "productUnitDetails") {


    $unitDetails = easySelectA(
        array(
            "table"  => "product_unit_variants",
            // "fields" => "puv_default as d, puv_name as n, puv_product_id as pid, round(puv_purchase_price, 2) as pp, round(puv_sale_price, 2) as sp",
            "fields" => "round(puv_purchase_price, 2) as pp, round(puv_sale_price, 2) as sp",
            "where"  => array(
                "puv_product_id" => $_GET["product_id"],
                " and puv_name"  => $_GET["unit"]
            )
        )
    )["data"][0];

    echo json_encode($unitDetails);

}


// Return shop income in the specific date 
if(isset($_GET['page']) and $_GET['page'] == "getShopIncome") {

   $todaySale = easySelect(
        "sales",
        "(sum(sales_grand_total) - sum(sales_due)) as chash_in",
        array(),
        array (
            "is_trash = 0 and sales_delivery_date"    => $_POST["incomeDate"],
            " AND sales_shop_id" => $_POST["shopId"]
        )
   );

   echo $todaySale["data"][0]["chash_in"];

}


// Employee Salary data
if(isset($_GET['page']) and $_GET['page'] == "getEmpSalaryData") {

    $emp_id = safe_input($_POST["empId"]);

    $employeeSalaryData = easySelectD(
        "SELECT 
            emp_id, emp_firstname, emp_lastname, emp_positions, round(emp_payable_salary, 2) as emp_payable_salary, round(emp_payable_overtime, 2) as emp_payable_overtime, round(emp_payable_bonus, 2) as emp_payable_bonus,
            loan_amount, loan_installment_amount, loan_id, 
            if(loan_installment_paid_amount is null, 0, loan_installment_paid_amount) as loan_installment_paid_amount
        from {$table_prefix}employees
        left join (select 
                loan_id, loan_borrower, loan_amount, loan_installment_amount 
            from {$table_prefix}loan where is_trash = 0 group by loan_id
        ) as loan on emp_id = loan_borrower
        left join (select 
                loan_ids, sum(loan_installment_paying_amount) as loan_installment_paid_amount 
            from {$table_prefix}loan_installment where is_trash = 0 group by loan_ids
        ) as loan_installment on loan_id = loan_ids
        where emp_id = {$emp_id}
        order by loan_id 
        desc limit 1"
    )["data"][0];

    echo json_encode($employeeSalaryData);
}


// Customer Payment Info
if(isset($_GET['page']) and $_GET['page'] == "getCustomerPaymentInfo") {

    $customer_id = safe_input($_POST["customerId"]);

    $customerPaymentsData = easySelectD(
        "SELECT customer_id, round(customer_opening_balance, 2) as customer_opening_balance,
            if(sales_grand_total is null, 0, round(sales_grand_total, 2)) as sales_grand_total, 
            if(sales_shipping is null, 0, round(sales_shipping, 2)) as sales_shipping, 
            if(sales_due is null, 0, round(sales_due, 2)) as sales_due, 
            if(returns_grand_total is null, 0, round(returns_grand_total, 2)) as returns_grand_total,
            if(received_payments_amount is null, 0, round(received_payments_amount, 2)) as total_received_payments,
            if(payments_return_amount is null, 0, round(payments_return_amount, 2)) as total_payment_return,
            if(received_payments_bonus is null, 0, round(received_payments_bonus, 2)) as total_given_bonus
        from {$table_prefix}customers
        left join ( select
                sales_customer_id,
                sum(sales_grand_total) as sales_grand_total,
                sum(sales_shipping) as sales_shipping,
                sum(sales_due) as sales_due
            from {$table_prefix}sales where is_return = 0 and is_trash = 0 and sales_status = 'Delivered' group by sales_customer_id
        ) as sales on customer_id = sales.sales_customer_id
        left join ( select 
                sales_customer_id, 
                sum(sales_grand_total) as returns_grand_total 
            from {$table_prefix}sales where is_return = 1 and is_trash = 0 and sales_status = 'Delivered' group by sales_customer_id
        ) as product_returns on customer_id = product_returns.sales_customer_id
        left join ( select 
                received_payments_from, 
                sum(received_payments_amount) as received_payments_amount, 
                sum(received_payments_bonus) as received_payments_bonus 
            from {$table_prefix}received_payments where is_trash = 0 group by received_payments_from
        ) as received_payments on customer_id = received_payments_from
        left join ( select
                payments_return_customer_id,
                sum(payments_return_amount) as payments_return_amount
            from {$table_prefix}payments_return where is_trash = 0 and payments_return_type = 'Outgoing' group by payments_return_customer_id
        ) as payment_return on customer_id = payments_return_customer_id
        where customer_id = {$customer_id}"
    )["data"][0];

    echo json_encode($customerPaymentsData);
}

// Customer Statement Info
if(isset($_GET['page']) and $_GET['page'] == "getCustomerStatementInfo") {

    $customer_id = safe_input($_POST["customerId"]);

    $dateRange = explode(" - ", safe_input($_POST["dateRange"]));


    $customerPaymentsData = easySelectD(
        "select customer_id, customer_name, customer_address, customer_district, district_name,
            ( 
                if(sales_total_amount is null, 0, sales_total_amount) + 
                if(wastage_sale_grand_total is null, 0, wastage_sale_grand_total) 
            ) as net_purchased, 
            if(sales_shipping is null, 0, sales_shipping) as total_shipping, 
            (
                if(sales_product_discount is null, 0, sales_product_discount) +
                if(sales_discount is null, 0, sales_discount)
            ) as total_purchased_discount,
            if(received_payments_amount is null, 0, received_payments_amount) as received_payments_amount,
            if(advance_payments_amount is null, 0, advance_payments_amount) as advance_payments_amount,
            if(sales_payments_amount is null, 0, sales_payments_amount) as sales_payments_amount,
            if(received_payments_bonus is null, 0, received_payments_bonus) as total_given_bonus,
            if(returns_grand_total is null, 0, returns_grand_total) as total_product_returns,
            if(discounts_amount is null, 0, discounts_amount) as special_discounts_amount,
            if(payments_return_amount is null, 0, payments_return_amount) as payments_return_amount
        from {$table_prefix}customers
        left join {$table_prefix}districts on district_id = customer_district
        left join ( select 
                sales_customer_id,  
                sum(case when is_return = 0 then sales_total_amount end) as sales_total_amount,
                sum(case when is_return = 0 then sales_shipping end) as sales_shipping,
                sum(case when is_return = 0 then sales_product_discount end) as sales_product_discount,
                sum(case when is_return = 0 then sales_discount end) as sales_discount,
                sum(case when is_return = 1 then sales_grand_total end) as returns_grand_total
            from {$table_prefix}sales where is_trash = 0 and sales_status = 'Delivered' and sales_delivery_date between '{$dateRange[0]}' and '{$dateRange[1]}' group by sales_customer_id
            ) as sales on customer_id = sales_customer_id
        left join ( select
                    wastage_sale_customer,
                    sum(wastage_sale_grand_total) as wastage_sale_grand_total
                from {$table_prefix}wastage_sale where is_trash = 0 and wastage_sale_date between '{$dateRange[0]}' and '{$dateRange[1]}' group by wastage_sale_customer
            ) as wastage_sale on customer_id = wastage_sale_customer
        left join ( select 
                        received_payments_from, 
                        sum(case when received_payments_type = 'Received Payments' then received_payments_amount end) as received_payments_amount, 
                        sum(case when received_payments_type = 'Advance Collection' then received_payments_amount end) as advance_payments_amount, 
                        sum(case when received_payments_type = 'Sales Payments' or received_payments_type = 'Wastage Sales Payments' then received_payments_amount end) as sales_payments_amount, 
                        sum(received_payments_bonus) as received_payments_bonus 
                    from {$table_prefix}received_payments 
                    where is_trash = 0 and received_payments_type != 'Discounts' and date(received_payments_datetime) between '{$dateRange[0]}' and '{$dateRange[1]}' 
                    group by received_payments_from
                ) as received_payments on customer_id = received_payments.received_payments_from
        left join ( select 
                received_payments_from, 
                sum(received_payments_amount) as discounts_amount 
            from {$table_prefix}received_payments 
            where is_trash = 0 and received_payments_type = 'Discounts' and date(received_payments_datetime) between '{$dateRange[0]}' and '{$dateRange[1]}' 
            group by received_payments_from
        ) as given_discounts on customer_id = given_discounts.received_payments_from
        left join (select
                payments_return_customer_id,
                sum(payments_return_amount) as payments_return_amount
            from {$table_prefix}payments_return
            where is_trash = 0 and payments_return_type = 'Outgoing' and date(payments_return_date) between '{$dateRange[0]}' and '{$dateRange[1]}' 
            group by payments_return_customer_id
        ) as payment_return on customer_id = payments_return_customer_id
        where customer_id = {$customer_id}"
    )["data"][0];

    $customerPaymentsData["previous_balance"] = easySelectD("
			SELECT 
				(
						if(customer_opening_balance is null, 0, customer_opening_balance) +						
						if(total_returned_before_filtered_date is null, 0, total_returned_before_filtered_date) +
						if(total_payment_before_filtered_date is null, 0, total_payment_before_filtered_date)
				) - ( 
						if(total_purchased_before_filtered_date is null, 0, total_purchased_before_filtered_date) +
                        if(total_wastage_purched_before_filtered_date is null, 0, total_wastage_purched_before_filtered_date) +
                        if(total_payment_return_before_filtered_date is null, 0, total_payment_return_before_filtered_date)
				) as previous_balance

			FROM {$table_prefix}customers as customers
			left join ( select
					sales_customer_id,
					sum(case when is_return = 0 then sales_grand_total end) as total_purchased_before_filtered_date,
                    sum(case when is_return = 1 then sales_due end) as total_returned_before_filtered_date
				from {$table_prefix}sales where is_trash = 0 and sales_status = 'Delivered' and sales_delivery_date < '{$dateRange[0]}' group by sales_customer_id
			) as sales on sales_customer_id = customer_id
            left join ( select
                    wastage_sale_customer,
                    sum(wastage_sale_grand_total) as total_wastage_purched_before_filtered_date
                from {$table_prefix}wastage_sale where is_trash = 0 and wastage_sale_date < '{$dateRange[0]}' group by wastage_sale_customer
            ) as wastage_sale on wastage_sale_customer = customer_id
			left join ( select 
					received_payments_from,
					sum(received_payments_amount) + sum(received_payments_bonus) as total_payment_before_filtered_date
				from {$table_prefix}received_payments where is_trash = 0 and date(received_payments_datetime) < '{$dateRange[0]}' group by received_payments_from
			) as payments on received_payments_from = customer_id
            left join (select
                    payments_return_customer_id,
                    sum(payments_return_amount) as total_payment_return_before_filtered_date
                from {$table_prefix}payments_return
                where is_trash = 0 and payments_return_type = 'Outgoing' and date(payments_return_date) < '{$dateRange[0]}'
                group by payments_return_customer_id
            ) as payment_return on customer_id = payments_return_customer_id
			where customer_id = {$customer_id}
	")["data"][0]["previous_balance"];

    echo json_encode($customerPaymentsData);
}

// Get Employee Loan Data
if(isset($_GET['page']) and $_GET['page'] == "getEmpLoanLoanData") {

    $emp_id = safe_input($_POST["empId"]);
    $month = safe_input($_POST["month"]);
    $year = safe_input($_POST["year"]);

    $getLoanDetails = easySelectD(
        "select 
            loan_id, loan_amount, loan_installment_amount, 
            if(thisMonthInstallmentPayingStatus is null, 0, 1) as thisMonthInstallmentPayingStatus, 
            if(loan_paid_amount is null, 0, loan_paid_amount) as loan_paid_amount 
        from {$table_prefix}loan as loan
        left join (select 
                loan_ids, 
                sum(loan_installment_paying_amount) as loan_paid_amount 
            from {$table_prefix}loan_installment where is_trash = 0 group by loan_ids
        ) as totalPaidAmount on loan_id = totalPaidAmount.loan_ids
        left join (select 
                loan_ids, 
                1 as thisMonthInstallmentPayingStatus
            from {$table_prefix}loan_installment where is_trash = 0 and MONTH(loan_installment_date) = {$month} and year(loan_installment_date) = {$year} group by loan_ids 
        ) as thisMonthStatus on loan_id = thisMonthStatus.loan_ids
        where loan.is_trash = 0 and loan_borrower = {$emp_id} and loan_installment_starting_from <= '{$year}-{$month}-01'
        and ( loan_paid_amount is null or loan_paid_amount < loan_amount)" 
        // loan_paid_amount can be NULL on left join if there is no recrods, for that the is null check.
        // We can also use HAVING cluese without using is null check. But it will raise a error with full_group_by mode.
    );

    $selectSalary = easySelectA(array(
        "table"     => "employees",
        "fields"    => "round(emp_salary, 2) as emp_salary",
        "where"     => array("emp_id = {$emp_id}")
    ))["data"][0]["emp_salary"];

    if(!isset($getLoanDetails["data"])) {
        
        $return = array (
            "totalInstallmentAmount"  => 0,
            "totalLoan"         => 0,
            "totalLoanPaid"     => 0,
            "salary"            => $selectSalary
       );
    
       echo json_encode($return);
       return; 
    }

    $loanInstallmentAmountInCurrentMonth = 0;
    $totalLoanAmount = 0;
    $totalLoanPaidAmount = 0;

    foreach($getLoanDetails["data"] as $key => $value) {

        // Check if the loan installment already not paid
        // then add the installment amount
        if($value["thisMonthInstallmentPayingStatus"] != 1) {
            $loanInstallmentAmountInCurrentMonth += ( $value["loan_amount"] - $value["loan_paid_amount"] >= $value["loan_installment_amount"] ) ? $value["loan_installment_amount"] : $value["loan_amount"] - $value["loan_paid_amount"];
        }

        $totalLoanAmount += $value["loan_amount"];
        $totalLoanPaidAmount += $value["loan_paid_amount"];
        
    }
    
   $return = array (
        "totalInstallmentAmount"  => $loanInstallmentAmountInCurrentMonth,
        "totalLoan"         => $totalLoanAmount,
        "totalLoanPaid"     => $totalLoanPaidAmount,
        "salary"            => $selectSalary
   );

   echo json_encode($return);

}


// Get Employee Advance Payments Data
if(isset($_GET['page']) and $_GET['page'] == "getEmployeeAdvancePaymentsData") {
    $emp_id = safe_input($_POST["empId"]);

    $getEmpAdvancePaymentData = easySelectD("
        select emp_id, emp_firstname, emp_lastname,
            if(advance_payment_amount_sum is null, 0, advance_payment_amount_sum) as advance_paid_amount,
            if(payments_return_amount_sum is null, 0, payments_return_amount_sum) + if(payment_amount_sum is null, 0, payment_amount_sum) as advance_adjust_amount
        from {$table_prefix}employees
        left join ( select 
                advance_payment_pay_to, 
                sum(advance_payment_amount) as advance_payment_amount_sum 
            from {$table_prefix}advance_payments where is_trash = 0 group by advance_payment_pay_to 
        ) as get_advance_payments on advance_payment_pay_to = emp_id
        left join ( select 
                payment_to_employee, 
                sum(payment_amount) as payment_amount_sum 
            from {$table_prefix}payments where is_trash = 0 and payment_type = 'Advance Adjustment' group by payment_to_employee 
        ) as get_payments on payment_to_employee = emp_id
        left join ( select 
                payments_return_emp_id, 
                sum(payments_return_amount) as payments_return_amount_sum 
            from {$table_prefix}payments_return where is_trash = 0 group by payments_return_emp_id 
        ) as get_advance_return on payments_return_emp_id = emp_id
        where emp_id = {$emp_id}"
    )["data"][0];
    
    echo json_encode($getEmpAdvancePaymentData);

}


// Get Company Due Bill Details
if(isset($_GET['page']) and $_GET['page'] == "getCompanyDueBillDetails") {
    $company_id = safe_input($_POST["company_id"]);

    $getEmpAdvancePaymentData = easySelectD("
        select company_id, round(company_opening_balance, 2) as company_opening_balance,
            (
                if(bills_amount_sum is null, 0, round(bills_amount_sum, 2)) +
                if(purchase_grand_total is null, 0, round(purchase_grand_total, 2))
            ) as bills_amount_sum,
            (
                if(payment_amount_sum is null, 0, round(payment_amount_sum, 2)) +
                if(purchase_return_grand_total is null, 0, purchase_return_grand_total)
            ) as payment_amount_sum,
            if(adjustment_amount is null, 0, round(adjustment_amount, 2)) as adjustment_amount_sum
        from {$table_prefix}companies
        left join ( select 
                bills_company_id, 
                sum(bills_amount) as bills_amount_sum 
            from {$table_prefix}bills where is_trash = 0 group by bills_company_id 
        ) as get_company_bills on bills_company_id = company_id 
        left join ( select
                payment_to_company, 
                sum(payment_amount) as payment_amount_sum 
            from {$table_prefix}payments where is_trash = 0 and ( payment_type = 'Due Bill' or payment_type = 'Bill' ) group by payment_to_company 
        ) as get_company_payment on payment_to_company = company_id
        left join (
            select
                pa_company,
                sum(pa_amount) as adjustment_amount
            from {$table_prefix}payment_adjustment where is_trash = 0 group by pa_company
        ) as payment_adjustment on pa_company = company_id
        left join (
            select
                purchase_company_id,
                sum(CASE WHEN is_return = 0 then purchase_grand_total end) as purchase_grand_total,
                sum(CASE WHEN is_return = 1 then purchase_due end) as purchase_return_grand_total
            from {$table_prefix}purchases where is_trash = 0 group by purchase_company_id
        ) as purchaseBill on purchaseBill.purchase_company_id = company_id
        where company_id = {$company_id}"
    )["data"][0];
    
    echo json_encode($getEmpAdvancePaymentData);

}


// Product Comparision Details

if(isset($_GET['page']) and $_GET['page'] == "getProductComparision") {

    $dateRange = explode(" - ", safe_input($_POST["dateRange"]));
    $product = implode(",",$_POST["productsId"]);


    // Check if the given products are variable or not
    $selectAllProduct = easySelectA(array(
        "table"     => "products",
        "fields"    => "product_type, product_id, child_product as child_product_list",
        "join"      => array(
            "left join (
                SELECT 
                    product_parent_id,
                    group_concat(product_id) as child_product
                FROM {$table_prefix}products
                where is_trash = 0
                group by product_parent_id
            ) as child_product on child_product.product_parent_id = product_id"
        ),
        "where"     => array(
            "product_id in($product)"
        )
    ));


    if($selectAllProduct !== false) {

        $productIDList = array();
        foreach($selectAllProduct["data"] as $product ) {

            if( $product["product_type"] === "Variable" ) {

                // If the product is variable, push the child_product_list in productIDList variable
                array_push($productIDList, $product["child_product_list"]);
        
            } else {
        
                // If the product is a normal product or any other type of product then push the product_id
                array_push($productIDList, $product["product_id"] );
        
            }

        }


        $product = implode(",", $productIDList);
        
    }

    


    $chart_label = array();
    $chart_data = array();

    $actProductAs = $_POST["actProduct"];

    if($actProductAs === "Same") {

        list($startYear, $startMonth, $startDay) = explode("-", $dateRange[0]);
        list($endYear, $endMonth, $endDay) = explode("-", $dateRange[1]);

        $label = "CONCAT( DAY(db_date), ', ', LEFT(MONTHNAME(db_date), 3))";
        if($_POST["groupBy"] === "Monthly") {
            $label = "LEFT(MONTHNAME(db_date), 3)";
        } else if($_POST["groupBy"] === "Yearly") {
            $label = "YEAR(db_date)";
        }
        
        $groupByOnStockEntry = "MONTH(stock_entry_date), DAY(stock_entry_date)";
        $groupByOnDbDate = "MONTH(db_date), DAY(db_date)";
        if($_POST["groupBy"] === "Monthly") {

            $groupByOnStockEntry = "MONTH(stock_entry_date)";
            $groupByOnDbDate = "MONTH(db_date)";

        } else if($_POST["groupBy"] === "Yearly") {

            $groupByOnStockEntry = "YEAR(stock_entry_date)";
            $groupByOnDbDate = "YEAR(db_date)";

        }


        $relationOnDate = "MONTH(stock_entry_date) = MONTH(db_date) and DAY(stock_entry_date) = DAY(db_date)";
        if($_POST["groupBy"] === "Monthly") {

            $relationOnDate = "MONTH(stock_entry_date) = MONTH(db_date)";

        } else if($_POST["groupBy"] === "Yearly") {

            $relationOnDate = "YEAR(stock_entry_date) = YEAR(db_date)";

        }

    
        $sales = easySelectD("
            SELECT
                product_id,
                product_name,
                {$label} as label,
                if(total_sale_qnt is null, 0, total_sale_qnt) as total_sale_qnt_sum
            FROM
                ro_products
            JOIN time_dimension 
            LEFT JOIN ( select 
                        stock_entry_date, 
                        stock_product_id, 
                        sum(stock_item_qty) as total_sale_qnt 
                    from ro_product_stock where is_trash = 0 and stock_type = 'sale' group by {$groupByOnStockEntry}, stock_product_id
                ) as sale_items ON {$relationOnDate} and stock_product_id = product_id
            WHERE
                product_id IN($product) and MONTH(db_date) between '{$startMonth}' and '{$endMonth}' and DAY(db_date) between '{$startDay}' and '{$endDay}'
            GROUP BY {$groupByOnDbDate}, product_id
            ORDER BY `time_dimension`.`db_date` ASC, product_id DESC
        ");

        //print_r($sales);
        

        foreach($sales["data"] as $key => $data) {

            // Collect Sold qunatity
            if( !isset($chart_data[$data["product_id"]]) ) {
    
                $chart_data[$data["product_id"]] = array(
                    "label"  => $data["product_name"],
                    "borderColor" => "#".substr(md5(rand()), 0, 6),
                    "borderWidth"  => 2,
                    "data"      => array($data["total_sale_qnt_sum"])
                );
    
            } else {
    
                array_push($chart_data[$data["product_id"]]["data"], $data["total_sale_qnt_sum"]);
    
            }
    
            // Collect Dates
            if(!in_array($data["label"], $chart_label)) {
    
                if( !in_array($data["label"], $chart_label) ) {
    
                    array_push($chart_label, $data["label"] );   
    
                }
    
    
            }
    
        }

        

    } else {

        $label = "db_date";
        if($_POST["groupBy"] === "Monthly") {
            $label = "CONCAT(LEFT(MONTHNAME(db_date), 3), ', ', YEAR(db_date))";
        } else if($_POST["groupBy"] === "Yearly") {
            $label = "YEAR(db_date)";
        }
        
        $groupBy = "stock_entry_date";
        if($_POST["groupBy"] === "Monthly") {
            $groupBy = "EXTRACT(YEAR_MONTH FROM stock_entry_date)";
        } else if($_POST["groupBy"] === "Yearly") {
            $groupBy = "YEAR(stock_entry_date)";
        }


        $relationOnDate = "db_date";
        if($_POST["groupBy"] === "Monthly") {
            $relationOnDate = "EXTRACT(YEAR_MONTH FROM db_date)";
        } else if($_POST["groupBy"] === "Yearly") {
            $relationOnDate = "YEAR(db_date)";
        }

        $sales = easySelectD("
            SELECT
                product_id,
                product_name,
                {$label} as label,
                db_date,
                if(total_sale_qnt is null, 0, total_sale_qnt) as total_sale_qnt_sum
            FROM
                ro_products
            JOIN time_dimension 
            LEFT JOIN ( select 
                        stock_entry_date, 
                        stock_product_id, 
                        sum(stock_item_qty) as total_sale_qnt 
                    from ro_product_stock where is_trash = 0 and stock_type = 'sale' group by {$groupBy}, stock_product_id
                ) as sale_items ON {$groupBy} = {$relationOnDate} and stock_product_id = product_id
            WHERE
                product_id IN($product) and db_date between '{$dateRange[0]}' and '{$dateRange[1]}'
            GROUP BY {$relationOnDate}, product_id
            ORDER BY `time_dimension`.`db_date` ASC, product_id DESC
        ")["data"];

        foreach($sales as $key => $data) {

            // Collect Sold qunatity
            if( !isset($chart_data[$data["product_id"]]) ) {
    
                $chart_data[$data["product_id"]] = array(
                    "label"  => $data["product_name"],
                    "borderColor" => "#".substr(md5(rand()), 0, 6),
                    "borderWidth"  => 2,
                    "data"      => array($data["total_sale_qnt_sum"])
                );
    
            } else {
    
                array_push($chart_data[$data["product_id"]]["data"], $data["total_sale_qnt_sum"]);
    
            }
    
            // Collect Dates
            if(!in_array($data["label"], $chart_label)) {
    
                if( !in_array($data["label"], $chart_label) ) {
    
                    array_push($chart_label, $data["label"] );   
    
                }
    
    
            }
    
        }

    }


    echo json_encode( array(
            "label"  => $chart_label,
            "dataset"  => $chart_data
        ));

}


if(isset($_GET['page']) and $_GET['page'] == "getAccountsInfo") {

    $selectAccounts = easySelectA(array(
        "table"    => "accounts",
        "fields"    => "accounts_name, round(accounts_balance, 2) as accounts_balance",
        "where"     => array(
            "accounts_id"   => $_POST["accountsId"]
        )
    ));

    $accountData = array();

    if($selectAccounts) {
        $accountData["name"] = $selectAccounts["data"][0]["accounts_name"];
        $accountData["balance"] = $selectAccounts["data"][0]["accounts_balance"];
    }

    echo json_encode($accountData);

}


if(isset($_GET['page']) and $_GET['page'] == "customerPurchaseList") {

    $invoiceSearch = ( isset($_GET["s"]) and !empty($_GET["s"]) ) ? "and sales_reference like '%{$_GET["s"]}%'" : "";

    $selectSales = easySelectA(array(
        "table"     => "sales",
        "fields"    => "sales_id as id, sales_status, DATE_FORMAT(sales_delivery_date, '%b %d, %Y') as date, sales_reference as ref, round(sales_grand_total, 2) as total, sales_payment_status as pay_status, shop_name as shop",
        "join"      => array(
            "left join {$table_prefix}shops on sales_shop_id = shop_id"
        ),
        "where"     => array(
            "sales_customer_id" => $_GET["cid"],
            "{$invoiceSearch}"
        ),
        "orderby"   => array(
            "sales_id"  => "DESC"
        ),
        "limit" => array(
            "start"     => 0,
            "length"    => 25
        )
    ));

    if($selectSales) {
        
        echo json_encode($selectSales["data"]);

    }

}


if(isset($_GET['page']) and $_GET['page'] == "customerPurchaseProductList") {

    $selectPurchaseProductBySalesId = easySelectA(array(
        "table"     => "product_stock",
        "fields"    => "stock_product_id as pid, product_name as pn, product_unit as pu, has_expiry_date as hed, product_generic as pg, if(stock_batch_id is null, '', stock_batch_id) as batch, round(stock_item_price, 2) as stock_item_price, round(stock_item_qty, 2) as stock_item_qty, round(stock_item_discount, 2) as stock_item_discount, round(stock_item_subtotal, 2) as stock_item_subtotal",
        "join"      => array(
            "left join {$table_prefix}products on stock_product_id = product_id",
            "left join {$table_prefix}product_batches on stock_batch_id = batch_id"
        ),
        "where"     => array(
            "stock_item_qty > 0 and stock_sales_id " => $_GET["saleid"]
        )
    ));

    if($selectPurchaseProductBySalesId) {
        
        echo json_encode($selectPurchaseProductBySalesId["data"]);

    }

}


if(isset($_GET['page']) and $_GET['page'] == "getCustomerData") {

    $customerData = easySelectA(array(
        "table"     => "customers",
        "fields"    => "round(customer_shipping_rate, 2) as shipping_rate, customer_discount as discount",
        "where"     => array(
            "customer_id"   => $_GET["cid"]
        )
    ));

    echo json_encode($customerData["data"][0]);

}


/************************** Update in line data **********************/
if(isset($_GET['page']) and $_GET['page'] == "updateInLine") {

    //print_r( $_REQUEST );

    /**
     * tab = table
     * p = prefeix
     * f = field
     * t = target (primary key field)
     * pkey = primary key
     */

    if( !isset($_GET["t"]) or !isset($_GET["f"]) or !isset($_GET["p"]) or !isset($_GET["tab"])  ) {

        echo '{
            "error": "true",
            "msg": "An unknow error occured. Please contact with administrator."
        }';

    } else if( empty($_GET["t"]) or empty($_GET["f"]) or empty($_GET["p"]) or empty($_GET["tab"])  ) {
        
        echo '{
            "error": "true",
            "msg": "An unknow error occured. Please contact with administrator.d"
        }';

    } else {

        // Update the
        $updateData = easyUpdate(
            safe_input($_GET["tab"]),
            array(
                safe_input($_GET["p"]).safe_input($_GET["f"]) => $_POST["newData"]
            ),
            array(
                safe_input($_GET["p"]).safe_input($_GET["t"]) => $_POST["pkey"]
            )
        );

        if($updateData === true) {
            
            echo '{
                "error": "false"
            }';

        } else {

            echo '{
                "error": "true",
                "msg": "'. $updateData .'"
            }';

        }

    }

}


/************************** Add New Capital **********************/
if(isset($_GET['page']) and $_GET['page'] == "getCustomerClosingsDate") {

    $closings = easySelectA(array(
        "table"     => "closings",
        "fields"    => "closings_date, closings_title",
        "where"     => array(
            "is_trash = 0 and closings_customer" => $_GET["cid"]
        ),
        "orderby"   => array(
            "closings_date" => "ASC"
        )
    ));

    
    if($closings !== false) {
            
        $closingData["Next Period"] = array(
            end($closings["data"])["closings_date"],
            date("Y-12-31")
        );

        $previous_data = "1970-01-00";
        foreach($closings["data"] as $closing) {
            
            $closingData[$closing["closings_title"]] = array(
                date("Y-m-d", strtotime($previous_data . ' +1 day')),
                $closing["closings_date"]
            );
            $previous_data = $closing["closings_date"];

        }

        echo json_encode($closingData);

    }

}



if(isset($_GET['page']) and $_GET['page'] == "productVisualList") {

    $productCategoryFilter = ( isset($_GET["catId"]) and !empty($_GET["catId"]) ) ? $_GET["catId"] : "";
    $productBrandFilter = ( isset($_GET["brand"]) and !empty($_GET["brand"]) ) ? $_GET["brand"] : "";
    $productEditionFilter = ( isset($_GET["edition"]) and !empty($_GET["edition"]) ) ? " AND product.product_edition = '{$_GET["edition"]}'" : " AND product_type != 'Child' ";
    $productGenericFilter = ( isset($_GET["generic"]) and !empty($_GET["generic"]) ) ? $_GET["generic"] : "";
    $productAuthorFilter = ( isset($_GET["author"]) and !empty($_GET["author"]) ) ? $_GET["author"] : "";
    $productTerms = ( isset($_GET["terms"]) and !empty($_GET["terms"]) ) ? " AND product_name like '%{$_GET["terms"]}%'" : "";
    $productSort = ( isset($_GET["sort"]) and !empty($_GET["sort"]) ) ? $_GET["sort"] : "";

    $oderBy = array(
        "product.product_id" => "DESC"
    );
    
    if( !empty($productSort) ) {

        if( $productSort === "1") {

            $oderBy = array(
                "total_sold_qty"  => "DESC",
                "id"           => "ASC"
            );

        } else if( $productSort === "2") {
            $oderBy = array(
                "total_stock"  => "ASC",
                "id"           => "ASC"
            );
        } else if( $productSort === "3") {
            $oderBy = array(
                "total_stock"  => "DESC",
                "id"           => "ASC"
            );
        }

    }
    
    $productList = easySelectA(array(
        "table"     => "products as product",
        "fields"    => "product.product_id as id, product_name as name, product_type, product_alert_qnt as alert, length(product_photo) as v, 

                        round( coalesce(child_stock_in, main_stock_in, 0), 2) as stock,
                        round( coalesce(child_sold_qty, main_sold_qty, 0), 2) as sold_qty,

                        round( coalesce(child_stock_in, edition_stock_in, main_stock_in, 0), 2) as total_stock,
                        round( coalesce(child_sold_qty, edition_sold_qty, main_sold_qty, 0), 2) as total_sold_qty",
        "where"     => array (
            "is_trash = 0 $productEditionFilter",
            " AND product_category_id"          => $productCategoryFilter,
            " AND product_brand_id"             => $productBrandFilter,
            " AND product_generic"              => $productGenericFilter,
            "{$productTerms}"
        ),
        "join"  => array(
            
            "left join (select
                    product_id,
                    sum(base_stock_in/ base_qty) as main_stock_in
                from product_base_stock
                group by product_id
            ) as stock using(product_id)",

            "left join (select
                    stock_product_id,
                    sum(stock_item_qty) as main_sold_qty
                from {$table_prefix}product_stock
                where is_trash = 0 and stock_type = 'sale'
                group by stock_product_id
            ) as sold on stock_product_id = product.product_id",

            "left join ( -- Variable product stock counting by all child product

                SELECT -- Child Product
                    product_parent_id,
                    group_concat(product_id) as child_products,
                    sum(stock_in) as child_stock_in,
                    sum(sold_qty) as child_sold_qty
                FROM {$table_prefix}products as childProductJoin

                left join (select 
                        product_id,
                        sum(base_stock_in/ base_qty) as stock_in
                    from product_base_stock
                    group by product_id
                ) as stock using(product_id)
                
                left join (select
                        stock_product_id,
                        sum(stock_item_qty) as sold_qty
                    from {$table_prefix}product_stock
                    where is_trash = 0 and stock_type = 'sale'
                    group by stock_product_id
                ) as sold on stock_product_id = childProductJoin.product_id

                where childProductJoin.is_trash = 0
                group by product_parent_id

            ) as child_product on child_product.product_parent_id = product_id",

            "LEFT JOIN (

                SELECT -- same edition Product counting
                    product_parent_id,
                    product_edition as test_edit,
                    product_edition,
                    sum(edition_stock_in) as edition_stock_in,
                    sum(edition_sold_qty) as edition_sold_qty
                FROM {$table_prefix}products as sameEditionProduct
    
                left join (select 
                        product_id,
                        sum(base_stock_in/ base_qty) as edition_stock_in
                    from product_base_stock
                    group by product_id
                ) as stock using(product_id)
                
                left join (select
                        stock_product_id,
                        sum(stock_item_qty) as edition_sold_qty
                    from {$table_prefix}product_stock
                    where is_trash = 0 and stock_type = 'sale'
                    group by stock_product_id
                ) as sold on stock_product_id = sameEditionProduct.product_id
    
                where sameEditionProduct.is_trash = 0
                group by product_parent_id, product_edition
    
                -- sep = same edition product
    
            ) as sep on (sep.product_parent_id = product.product_parent_id and sep.product_edition = product.product_edition)",

        ),
        "orderby"   => $oderBy,
        "limit"     => array (
            "start"     => 0,
            "length"    => empty(get_options("maxProductDisplay")) ? 100 : get_options("maxProductDisplay")
        )
        
    ));
    
    if($productList) {
        
        echo json_encode($productList["data"]);

    } else {
        echo "null";
    }

}


if(isset($_GET['page']) and $_GET['page'] == "getChildProductData") {


    $warehouse_filter = empty($_GET["wid"]) ? "" : " = " . safe_input($_GET["wid"]);

    $getChildProductData = easySelectA(array(
        "table"     => "products as product",
        "fields"    => "
                        product.product_id as pid, product_type, concat(product_name, ' ', if(product_group is null, '', product_group) ) as product_name, 
                        brand_name, product_purchase_price, product_sale_price, product_edition, product_unit, product_category_id, category_name,
                        round( coalesce(initial_qty, 0), 2) as initial_qty,
                        round( coalesce(production_qty, 0), 2) as production_qty,
                        round( coalesce(sale_qty, 0), 2) as sale_qty,
                        round( coalesce(wastage_sale_qty, 0), 2) as wastage_sale_qty,
                        round( coalesce(sale_return_qty, 0), 2) as sale_return_qty,
                        round( coalesce(purchase_qty, 0), 2) as purchase_qty,
                        round( coalesce(purchase_order_qty, 0), 2) as purchase_order_qty,
                        round( coalesce(purchase_return_qty, 0), 2) as purchase_return_qty,
                        round( coalesce(transfer_in_qty, 0), 2) as transfer_in_qty,
                        round( coalesce(transfer_out_qty, 0), 2) as transfer_out_qty,
                        round( coalesce(specimen_copy_qty, 0), 2) as specimen_copy_qty,
                        round( coalesce(specimen_copy_return_qty, 0), 2) as specimen_copy_return_qty,
                        round( coalesce(expired_qty, 0), 2) as expired_qty,
                        round( coalesce(stock_qty, 0), 2) as stock_qty,
                        round( coalesce(sale_item_subtotal, 0), 2) as total_sold_amount,
                        round( coalesce(purchase_item_subtotal, 0), 2) as total_purchased_amount,
                        round( coalesce(sale_qty_in_range, 0), 2) as sale_qty_in_range,
                        round( coalesce(specimen_copy_qty, 0), 2) as specimen_copy_qty
        ",
        "join"      => array(
            "left join (
                select
                    stock_product_id,
                    sum(case when stock_type = 'initial' then stock_item_qty end) as initial_qty,
                    sum(case when stock_type = 'sale-production' then stock_item_qty end) as production_qty,
                    sum(case when stock_type = 'sale' then stock_item_qty end) as sale_qty,
                    sum(case when stock_type = 'sale' then stock_item_qty end) as sale_qty_in_range,
                    sum(case when stock_type = 'sale' then stock_item_subtotal end) as sale_item_subtotal,
                    sum(case when stock_type = 'wastage-sale' then stock_item_qty end) as wastage_sale_qty,
                    sum(case when stock_type = 'sale-return' then stock_item_qty end) as sale_return_qty,
                    sum(case when stock_type = 'purchase' then stock_item_qty end) as purchase_qty,
                    sum(case when stock_type = 'purchase' then stock_item_subtotal end) as purchase_item_subtotal,
                    sum(case when stock_type = 'purchase-order' then stock_item_qty end) as purchase_order_qty,
                    sum(case when stock_type = 'purchase-return' then stock_item_qty end) as purchase_return_qty,
                    sum(case when stock_type = 'transfer-in' then stock_item_qty end) as transfer_in_qty,
                    sum(case when stock_type = 'transfer-out' then stock_item_qty end) as transfer_out_qty,
                    sum(case when stock_type = 'specimen-copy' then stock_item_qty end) as specimen_copy_qty,
                    sum(case when stock_type = 'specimen-copy-return' then stock_item_qty end) as specimen_copy_return_qty
                from {$table_prefix}product_stock
                where is_trash = 0 and stock_warehouse_id $warehouse_filter
                group by stock_product_id
            ) as product_stock on stock_product_id = product_id",
            "left join (
                select
                    vp_id,
                    sum(case when batch_expiry_date < curdate() then base_stock_in/base_qty end) as expired_qty,
                    sum(case when batch_expiry_date is null or batch_expiry_date > curdate() then base_stock_in/base_qty end) as stock_qty
                from product_base_stock
                where warehouse $warehouse_filter
                group by vp_id
            ) as base_stock on base_stock.vp_id = product.product_id",

            "left join {$table_prefix}product_category on product_category_id = category_id",
            "left join {$table_prefix}product_brands on product_brand_id = brand_id",
        ),
        "where"     => array(
            "product.is_trash = 0 and product.product_parent_id" => $_GET["pid"]
        ),
        "groupby"   => "product.product_id",
    ));

    if($getChildProductData !== false) {


        $allData = [];

        foreach($getChildProductData['data'] as $key => $value) {

            
            $allNestedData = [];

            $allNestedData[] = "";
                
            $allNestedData[] = "<a title='Show More Details' href='". full_website_address() ."/reports/product-report/?pid={$value['pid']}'>{$value['product_name']}</a> 
                            <a title='Update stock' style='padding-left: 5px; color: #a1a1a1;' class='updateEntry' href='". full_website_address() . "/xhr/?module=reports&page=updateProductStock' data-to-be-updated='". $value["pid"] ."'><i class='fa fa-refresh'></i></a>";
            $allNestedData[] = $value["brand_name"];
            $allNestedData[] = $value["category_name"];
            $allNestedData[] = $value["product_edition"];
            $allNestedData[] = $value["initial_qty"];
            $allNestedData[] = $value["production_qty"];
            $allNestedData[] = number_format($value["purchase_qty"], 2) ;
            $allNestedData[] = $value["purchase_return_qty"];
            $allNestedData[] = $value["transfer_in_qty"];
            $allNestedData[] = $value["transfer_out_qty"];
            $allNestedData[] = number_format($value["sale_qty"], 2);
            $allNestedData[] = $value["sale_qty_in_range"];
            $allNestedData[] = $value["sale_return_qty"];
            $allNestedData[] = $value["specimen_copy_qty"];
            $allNestedData[] = $value["specimen_copy_return_qty"];
            $allNestedData[] = $value["expired_qty"];
            $allNestedData[] = number_format($value["stock_qty"], 2);
            $allNestedData[] = $value["product_unit"];
            $allNestedData[] = $value["stock_qty"] * $value["product_sale_price"];
            $allNestedData[] = $value["stock_qty"] * $value["product_purchase_price"];
            $allNestedData[] = $value["total_purchased_amount"];
            $allNestedData[] = $value["total_sold_amount"];

            
            $allData[] = $allNestedData;

        }


        echo json_encode($allData);


    } else {

        echo 0;

    }

}




if(isset($_GET['page']) and $_GET['page'] == "salesOverviewChartData") {

    $type = isset($_GET["type"]) ? $_GET["type"] : "daily";

    if( $type === "weekly" ) {

        $weeklySalesData = easySelectD("
            SELECT
                concat(date_format(db_date, '%D %M')) AS label,
                if(sales_quantity is null, 0, sum(sales_quantity)) as sales_quantity
            FROM time_dimension
            LEFT JOIN (
                SELECT 
                    sales_delivery_date, 
                    sum(sales_quantity) as sales_quantity 
                FROM {$table_prefix}sales 
                WHERE is_trash = 0
                GROUP BY sales_delivery_date
            ) AS sales on sales_delivery_date = db_date
            WHERE db_date BETWEEN NOW() - INTERVAL 30 WEEK AND NOW()
            group by week(db_date)
		");

        $weeklySalesOverviewLabel = array();
		$weeklySalesOverviewData = array();
        
        if( $weeklySalesData !== false ) {

            foreach($weeklySalesData["data"] as $sales ) {
                array_push($weeklySalesOverviewLabel, $sales["label"] );
                array_push($weeklySalesOverviewData, $sales["sales_quantity"] );
            }

        }


        $weeklySalesData = array(
            "labels" => $weeklySalesOverviewLabel,
            "datasets" => array(
                array(
                    "label" => __("Weekly Sales"),
                    "borderColor" => "green",
                    "borderWidth"   => 2,
                    "data"  => $weeklySalesOverviewData
                )
            )
        );

        echo json_encode($weeklySalesData);


    } else {

        
        /** Daily Sales Calculatin */

        $dailySalesData = easySelectD("
            SELECT
                db_date AS label,
                if(sales_quantity is null, 0, sales_quantity) as sales_quantity
            FROM time_dimension
            LEFT JOIN (
                SELECT 
                    sales_delivery_date, 
                    sum(sales_quantity) as sales_quantity 
                FROM {$table_prefix}sales 
                WHERE is_trash = 0
                GROUP BY sales_delivery_date
            ) AS sales on sales_delivery_date = db_date
            WHERE db_date BETWEEN NOW() - INTERVAL 30 DAY AND NOW()
		");

        $dailySalesOverviewLabel = array();
		$dailySalesOverviewData = array();
        
        if( $dailySalesData !== false ) {

            foreach($dailySalesData["data"] as $sales ) {
                array_push($dailySalesOverviewLabel, $sales["label"] );
                array_push($dailySalesOverviewData, $sales["sales_quantity"] );
            }

        }


        $dailySalesData = array(
            "labels" => $dailySalesOverviewLabel,
            "datasets" => array(
                array(
                    "label" => __("Daily Sales"),
                    "borderColor" => "green",
                    "borderWidth"   => 2,
                    "data"  => $dailySalesOverviewData
                )
            )
        );

        echo json_encode($dailySalesData);

    }


}

?>