<div class="auth-slideshow" x-data="{
    active: 0,
    timer: null,
    paused: false,
    slides: [
        {
            image: '{{ asset('images/auth/slide-1.jpg') }}',
            tag: 'Dex Headquarters',
            title: 'Dex International Co.',
            desc: 'Precision engineering, installation management, and technical logistics.'
        },
        {
            image: '{{ asset('images/auth/slide-2.jpg') }}',
            tag: 'Operations & Logistics',
            title: 'Freight & Supply Chain',
            desc: 'Real-time equipment dispatch, inventory handling, and site deliveries.'
        },
        {
            image: '{{ asset('images/auth/slide-3.jpg') }}',
            tag: 'Engineering Excellence',
            title: 'Specialized Installations',
            desc: 'Panoramic architectural elevator systems and structural execution.'
        }
    ],
    init() {
        this.timer = setInterval(() => {
            if (!this.paused) {
                this.next();
            }
        }, 5500);
    },
    next() {
        this.active = (this.active + 1) % this.slides.length;
    },
    prev() {
        this.active = (this.active - 1 + this.slides.length) % this.slides.length;
    },
    goTo(index) {
        this.active = index;
    }
}" @mouseenter="paused = true" @mouseleave="paused = false">

    {{-- Slide 1 --}}
    <div class="auth-slide" :class="{ 'is-active': active === 0 }" class="auth-slide is-active">
        <img src="{{ asset('images/auth/slide-1.jpg') }}" alt="Dex International Co. Headquarters and Operations Hub" class="auth-slide-img" loading="eager">
        <div class="auth-slide-overlay">
            <span class="auth-slide-tag">Dex Headquarters</span>
            <h2 class="auth-slide-title">Dex International Co.</h2>
            <p class="auth-slide-desc">Precision engineering, installation management, and technical logistics.</p>
        </div>
    </div>

    {{-- Slide 2 --}}
    <div class="auth-slide" :class="{ 'is-active': active === 1 }">
        <img src="{{ asset('images/auth/slide-2.jpg') }}" alt="Dex International Co. Logistics and Freight Operations" class="auth-slide-img" loading="lazy">
        <div class="auth-slide-overlay">
            <span class="auth-slide-tag">Operations &amp; Logistics</span>
            <h2 class="auth-slide-title">Freight &amp; Supply Chain</h2>
            <p class="auth-slide-desc">Real-time equipment dispatch, inventory handling, and site deliveries.</p>
        </div>
    </div>

    {{-- Slide 3 --}}
    <div class="auth-slide" :class="{ 'is-active': active === 2 }">
        <img src="{{ asset('images/auth/slide-3.jpg') }}" alt="Dex International Co. Glass Elevator Installation Project" class="auth-slide-img" loading="lazy">
        <div class="auth-slide-overlay">
            <span class="auth-slide-tag">Engineering Excellence</span>
            <h2 class="auth-slide-title">Specialized Installations</h2>
            <p class="auth-slide-desc">Panoramic architectural elevator systems and structural execution.</p>
        </div>
    </div>

    {{-- Slideshow Controls (Dot Indicators) --}}
    <div class="auth-slideshow-controls">
        <div class="auth-slideshow-dots" role="tablist" aria-label="Slideshow indicators">
            <button
                type="button"
                class="auth-slideshow-dot"
                :class="{ 'is-active': active === 0 }"
                @click="goTo(0)"
                aria-label="Go to slide 1"
            ></button>
            <button
                type="button"
                class="auth-slideshow-dot"
                :class="{ 'is-active': active === 1 }"
                @click="goTo(1)"
                aria-label="Go to slide 2"
            ></button>
            <button
                type="button"
                class="auth-slideshow-dot"
                :class="{ 'is-active': active === 2 }"
                @click="goTo(2)"
                aria-label="Go to slide 3"
            ></button>
        </div>
    </div>

</div>
