/**
 * Change allowed days for delivery based on shipping method and product category.
 *
 * @param array $allowed_days
 * @return array
 */
function iconic_change_allowed_days($allowed_days) {
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    $chosen_shipping = !empty($chosen_methods) ? $chosen_methods[0] : null;

    // Check if 'flat_rate:3' (allow all days) is selected as the shipping method
    $override_shipping_all_days = 'flat_rate:3';

    // Check if 'flat_rate:2' (allow only Saturday) is selected as the shipping method
    $override_shipping_saturday = 'flat_rate:2';

    // Check if product category 'frozen' is in the cart
    $product_category_frozen = iconic_is_product_category_in_cart('frozen');

    // Initialize the days array
    $days = array(
        0 => false, // Sunday
        1 => false, // Monday
        2 => false, // Tuesday
        3 => false, // Wednesday
        4 => false, // Thursday
        5 => false, // Friday
        6 => false, // Saturday
    );

    if (!is_null($chosen_shipping)) {
        if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method']) && isset($_POST['shipping_method'][0])) {
            $shipping_method = $_POST['shipping_method'][0];
        } elseif (!empty($chosen_methods)) {
            $shipping_method = $chosen_methods[0];
        } else {
            $shipping_method = null;
        }

        if ($shipping_method === $override_shipping_all_days || ($shipping_method === $override_shipping_saturday && !$product_category_frozen)) {
            // If 'flat_rate:3' is selected or 'flat_rate:2' is selected and the product category is not 'frozen', allow all days.
            foreach ($days as $day => $value) {
                $days[$day] = true;
            }
        } elseif ($shipping_method === $override_shipping_saturday || ($shipping_method !== $override_shipping_saturday && $product_category_frozen)) {
            // If 'flat_rate:2' is selected or 'flat_rate:2' is not selected and the product category is 'frozen', allow only Saturday.
            $days[6] = true; // Saturday
        }
    }

    return $days;
}

/**
 * Check if a product category is in the cart.
 *
 * @param string $category_slug
 * @return bool
 */
function iconic_is_product_category_in_cart($category_slug) {
    // Initialize the result variable
    $category_in_cart = false;

    // Get the WooCommerce cart
    $cart = WC()->cart;

    // Loop through cart items
    foreach ($cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];

        // Get product categories for the current product
        $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));

        // Check if the desired category slug exists in the product categories
        if (in_array($category_slug, $product_categories)) {
            $category_in_cart = true;
            break; // Stop the loop if the category is found in the cart
        }
    }

    return $category_in_cart;
}

/**
 * Conditionally modify the same/next day cutoff time based on shipping methods and individual days.
 */
function iconic_conditionally_modify_cutoff_time() {
    global $iconic_wds;

    if (empty($iconic_wds) || empty(WC()->session)) {
        return;
    }

    $shipping_methods = WC()->session->get('chosen_shipping_methods');

    if (!isset($shipping_methods[0]) && isset($_POST['shipping_method'][0])) {
        return;
    }

    $shipping_method = isset($_POST['shipping_method'][0]) ? $_POST['shipping_method'][0] : (isset($shipping_methods[0]) ? $shipping_methods[0] : null);

    // Define the cutoff times based on shipping methods and individual days.
    $cutoff_times = array(
        0 => '14:00', // Sunday
        1 => '14:00', // Monday
        2 => '15:00', // Tuesday
        3 => '14:00', // Wednesday
        4 => '15:00', // Thursday
        5 => '14:00', // Friday
        6 => '14:00', // Saturday
    );

    // Check if 'flat_rate:2' is selected as the shipping method
    if ($shipping_method === 'flat_rate:2') {
        $current_day = date('w'); // Get the current day (0 for Sunday, 1 for Monday, etc.)

        // Set the same/next day cutoff times based on the current day
        $iconic_wds->settings['datesettings_datesettings_sameday_cutoff'] = $cutoff_times[$current_day];
        $next_day = ($current_day + 1) % 7; // Wrap around to Sunday if it's Saturday
        $iconic_wds->settings['datesettings_datesettings_nextday_cutoff'] = $cutoff_times[$next_day];
    } else {
        // For other shipping methods, you can set a default cutoff time if needed.
        // For example:
        // $iconic_wds->settings['datesettings_datesettings_sameday_cutoff'] = '00:00';
        // $iconic_wds->settings['datesettings_datesettings_nextday_cutoff'] = '00:00';
    }
}

/**
 * Refresh the checkout page when the user changes the postcode and fills billing info.
 */
add_action('wp_footer', 'refresh_on_postcode_change');
function refresh_on_postcode_change() {
    ?>
    <script type="text/javascript">
        jQuery(function ($) {
            // Listen for input in the postcode field
            $('input#billing_postcode').on('input', function () {
                // Store billing information in session storage
                var billingInfo = <?php echo json_encode($_POST); ?>;
                sessionStorage.setItem('billing_information', JSON.stringify(billingInfo));

                // Store the new postcode, first name, and last name in session storage
                var newPostcode = $(this).val();
                sessionStorage.setItem('billing_postcode', newPostcode);

                var firstName = $('input#billing_first_name').val();
                sessionStorage.setItem('billing_first_name', firstName);

                var lastName = $('input#billing_last_name').val();
                sessionStorage.setItem('billing_last_name', lastName);

                // Reload the page with a 2-second delay
                setTimeout(function () {
                    location.reload();
                }, 5000); // Delay for 5 seconds (adjust as needed)
            });

            // Check if billing information is stored in session storage
            var storedBillingInfo = sessionStorage.getItem('billing_information');
            if (storedBillingInfo) {
                // Parse the stored billing information
                var billingInfoObj = JSON.parse(storedBillingInfo);

                // Fill in the billing fields
                $.each(billingInfoObj, function (key, value) {
                    $('input[name="' + key + '"]').val(value);
                });

                // Clear the stored billing information
                sessionStorage.removeItem('billing_information');
            }

            // Check if a new postcode, first name, and last name are stored in session storage
            var storedPostcode = sessionStorage.getItem('billing_postcode');
            if (storedPostcode) {
                // Fill in the postcode field with the new postcode
                $('input#billing_postcode').val(storedPostcode);
            }

            var storedFirstName = sessionStorage.getItem('billing_first_name');
            if (storedFirstName) {
                // Fill in the first name field with the stored first name
                $('input#billing_first_name').val(storedFirstName);
            }

            var storedLastName = sessionStorage.getItem('billing_last_name');
            if (storedLastName) {
                // Fill in the last name field with the stored last name
                $('input#billing_last_name').val(storedLastName);
            }
        });
    </script>
    <?php
}

// Add both actions
add_filter('iconic_wds_allowed_days', 'iconic_change_allowed_days');
add_action('wp_loaded', 'iconic_conditionally_modify_cutoff_time', 11);
