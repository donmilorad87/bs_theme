/**
 * CT Team Members — Frontend handler.
 *
 * - "Meet Our Team" button reveals hidden members; toggles to "Hide".
 * - "Hide" button collapses back to initial state.
 * - Detects last-of-row tiles and flips popup direction.
 * - Supports multiple block instances on the same page.
 */
(function () {
    'use strict';

    var MAX_BLOCKS  = 20;
    var MAX_MEMBERS = 200;

    var blocks = document.querySelectorAll('.wp-block-ct-custom-team-members');
    var blockCount = 0;

    for (var b = 0; b < blocks.length; b++) {
        if (blockCount >= MAX_BLOCKS) {
            break;
        }
        blockCount++;

        initBlock(blocks[b]);
    }

    /**
     * Initialize a single team-members block instance.
     */
    function initBlock(block) {
        var btn = block.querySelector('.team-members__showmore-btn');
        var expanded = false;

        if (btn) {
            var showmore = btn.closest('.team-members__showmore');

            btn.addEventListener('click', function () {
                var showText = btn.getAttribute('data-show-text') || 'Meet Our Team';
                var hideText = btn.getAttribute('data-hide-text') || 'Hide';

                if (!expanded) {
                    /* Expand: show all rows */
                    block.classList.add('team-members--expanded');

                    if (showmore) {
                        showmore.classList.add('team-members__showmore--flat');
                    }

                    btn.textContent = hideText;
                    expanded = true;
                } else {
                    /* Collapse: back to 2 rows */
                    block.classList.remove('team-members--expanded');

                    if (showmore) {
                        showmore.classList.remove('team-members__showmore--flat');
                    }

                    btn.textContent = showText;
                    expanded = false;
                }

                /* Recalculate last-of-row after toggling */
                markLastOfRow(block);
            });
        }

        /* Mark last-of-row on load */
        markLastOfRow(block);

        /* Recalculate on resize */
        var resizeTimer = null;
        window.addEventListener('resize', function () {
            if (resizeTimer) {
                clearTimeout(resizeTimer);
            }
            resizeTimer = setTimeout(function () {
                markLastOfRow(block);
            }, 150);
        });
    }

    /**
     * Detect which tiles are the last visible in each row
     * and add .team-member--last-of-row so the popup flips direction.
     */
    function markLastOfRow(block) {
        var members = block.querySelectorAll('.team-member');
        var count = 0;
        var prevTop = -1;
        var prevMember = null;

        for (var i = 0; i < members.length; i++) {
            if (count >= MAX_MEMBERS) {
                break;
            }
            count++;

            /* Remove existing marker */
            members[i].classList.remove('team-member--last-of-row');

            var rect = members[i].getBoundingClientRect();
            var top  = Math.round(rect.top);

            /* If this tile is on a new row, mark the previous tile as last-of-row */
            if (prevTop !== -1 && top !== prevTop && prevMember) {
                prevMember.classList.add('team-member--last-of-row');
            }

            prevTop    = top;
            prevMember = members[i];
        }

        /* Mark the very last tile as last-of-row too */
        if (prevMember) {
            prevMember.classList.add('team-member--last-of-row');
        }
    }
})();
