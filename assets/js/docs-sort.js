jQuery(document).ready(function($) {
    // Make each category group an accordion section
    $("#sortable-accordion").accordion({
        header: "> .accordion-group > h3",
        collapsible: true,
        active: false,
        heightStyle: "content"
    });

    // Make the category accordions sortable
    $("#sortable-accordion").sortable({
        handle: "h3",
        items: ".accordion-group"
    });

    // Make docs sortable inside each category
    $(".docs-sortable").sortable({
        connectWith: ".docs-sortable"
    });

    // Save button
    $("#save-docs-order").on("click", function() {
        const allOrders = [];

        // Collect doc order per category
        $(".docs-sortable").each(function() {
            const categoryId = $(this).data("category-id");
            const docIds = [];

            $(this).children("li").each(function() {
                docIds.push($(this).data("id"));
            });

            allOrders.push({
                category_id: categoryId,
                docs: docIds
            });
        });

        // Get category IDs in their new order
        const sortedCategories = $("#sortable-accordion .accordion-group").map(function() {
            return $(this).data("id");
        }).get();

        // Send AJAX
        $.post(docsSortAjax.ajax_url, {
            action: 'save_docs_order',
            nonce: docsSortAjax.nonce,
            orders: allOrders,
            sortedCategories: sortedCategories
        }, function(response) {
            if (response.success) {
                $("#save-message").html('<p style="color:green;">Order saved successfully!</p>');
            } else {
                $("#save-message").html('<p style="color:red;">Failed to save order.</p>');
            }
        });
    });
});
