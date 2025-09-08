jQuery(document).ready(function ($) {
    // initialize letter tabs
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    const $display = $('.perpetual-data-display');
    let $items = $display.find('.perpetual-data-item').detach();

    const letterTabs = $('<div class="letter-tabs"></div>');
    letters.forEach(letter => {
        const $tab = $('<div class="letter-tab">' + letter + '</div>');
        $tab.on('click', function () {
            $('.letter-tab').removeClass('active');
            $(this).addClass('active');
            filterItems(letter);
        });
        letterTabs.append($tab);
    });
    $display.before(letterTabs); // insert tabs before the div containing items

    // group items by first letter
    const groupedItems = {};
    $items.each(function () {
        const firstLetter = $(this).find('.perpetual-entry').text().charAt(0).toUpperCase(); // get the first letter from entry
        if (!groupedItems[firstLetter]) groupedItems[firstLetter] = []; // initialize array if not exists
        groupedItems[firstLetter].push(this); // add item to the group
    });

    // add grouped items to a separate div for each letter
    $.each(groupedItems, function (letter, items) {
        const $group = $('<div class="filtered-items" data-letter="' + letter + '"></div>');
        $group.append(items);
        $display.append($group);
    });

    // trigger click on the first tab to show initial items
    $('.letter-tab').first().trigger('click');

    function filterItems(letter) {
        $('.no-items').hide();
        $('.filtered-items').removeClass('active');

        if ($('.filtered-items[data-letter="' + letter + '"]').length) {
            $('.filtered-items[data-letter="' + letter + '"]').addClass('active');
        } else {
            $('.no-items').show();
        }
    }
});