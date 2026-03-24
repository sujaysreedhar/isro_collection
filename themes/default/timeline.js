const viewport = document.getElementById('timeline-viewport');

function scrollToItem(idx) {
    const cards = viewport.querySelectorAll('.group');
    if (cards[idx]) {
        cards[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
}

// Scroll drag functionality
let isDown = false;
let startX;
let scrollLeft;

if (viewport) {
    viewport.addEventListener('mousedown', (e) => {
        isDown = true;
        viewport.classList.add('cursor-grabbing');
        startX = e.pageX - viewport.offsetLeft;
        scrollLeft = viewport.scrollLeft;
    });
    viewport.addEventListener('mouseleave', () => {
        isDown = false;
        viewport.classList.remove('cursor-grabbing');
    });
    viewport.addEventListener('mouseup', () => {
        isDown = false;
        viewport.classList.remove('cursor-grabbing');
    });
    viewport.addEventListener('mousemove', (e) => {
        if(!isDown) return;
        e.preventDefault();
        const x = e.pageX - viewport.offsetLeft;
        const walk = (x - startX) * 2;
        viewport.scrollLeft = scrollLeft - walk;
    });

    viewport.addEventListener('scroll', () => {
        const totalScroll = viewport.scrollWidth - viewport.clientWidth;
        const currentScroll = viewport.scrollLeft;
        const percent = totalScroll > 0 ? (currentScroll / totalScroll) * 100 : 0;
        const navIndicator = document.getElementById('nav-indicator');
        if (navIndicator) {
            navIndicator.style.width = percent + '%';
        }
    });
}
