<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#B01D54" />
  <meta name="description" content="EL Glamorous Face and Body Clinic — bring out the beauty in you." />
  <title>EL Glamorous Face and Body Clinic | Face, Body, Hair & Nails</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body>
  <div class="topbar"><div class="container topbar-inner"><span>Now welcoming appointments across Metro Cebu</span><span>Mon–Sun · 11:00 AM–8:00 PM at Canduman</span></div></div>
  <header>
    <div class="container nav">
      <a class="brand" href="#home" aria-label="EL Glamorous Face and Body Clinic home"><img src="{{ asset('images/logo.png') }}" alt="EL Glamorous Face and Body Clinic logo"><span class="brand-name">EL Glamorous Face and Body Clinic<small>FACE · BODY · HAIR · NAILS</small></span></a>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>
      <nav aria-label="Primary navigation"><a href="#services">Services</a><a href="#branches">Branches</a><a href="#about">About us</a><a class="btn btn-light" href="{{ route('login') }}">Sign in</a><a class="btn btn-light" href="#book">Book now <span>↗</span></a></nav>
    </div>
  </header>

  <main>
    <section id="home" class="hero">
      <div class="container hero-content">
        <p class="eyebrow">Face & body clinic · salon & spa</p>
        <h1>Bring out the <span class="script">beauty</span> in you.</h1>
        <p class="hero-copy">A little time for yourself can go a long way. Discover thoughtful treatments for your face, body, hair and nails across our Metro Cebu branches.</p>
        <div class="hero-actions"><a class="btn btn-primary" href="#book">Book an appointment <span>↗</span></a><a class="btn btn-light" href="#services">Explore services</a></div>
        <div class="hero-note"><span>✦</span><span><b>Three convenient branches</b> · Mandaue, Lapu-Lapu & Cebu City</span></div>
      </div>
    </section>

    <section id="services">
      <div class="container">
        <div class="section-head"><div><p class="eyebrow">Your self-care menu</p><h2>Care that feels<br><span class="script">personal.</span></h2></div><p class="section-lead">From everyday grooming to feel-good rituals, our menu is made to help you leave feeling refreshed, confident and completely yourself.</p></div>
        <div class="services-grid">
          <article class="service-card"><div class="service-icon">✦</div><h3>Face</h3><p>Facials, skin treatments and gentle glow-ups designed for your skin goals.</p><a href="#book" data-service="Face">View treatments →</a></article>
          <article class="service-card"><div class="service-icon">◌</div><h3>Body</h3><p>Relaxing body care and contouring treatments for a lighter, softer feeling.</p><a href="#book" data-service="Body">View treatments →</a></article>
          <article class="service-card"><div class="service-icon">⌁</div><h3>Hair</h3><p>Beautiful cuts, color and styling for the version of you that feels most alive.</p><a href="#book" data-service="Hair">View treatments →</a></article>
          <article class="service-card"><div class="service-icon">♡</div><h3>Nails</h3><p>Polished essentials and little details that complete your look.</p><a href="#book" data-service="Nails">View treatments →</a></article>
        </div>
      </div>
    </section>

    <section id="about" class="story">
      <div class="container story-grid">
        <div class="story-image"><img src="{{ asset('images/story.png') }}" alt="EL Glamorous Face and Body Clinic Pajac branch banner with beauty treatment imagery"><div class="image-chip">A moment made for you</div></div>
        <div class="story-copy"><p class="eyebrow">The EL Glamorous Face and Body Clinic feeling</p><h2>Look good.<br><span class="script">Feel even better.</span></h2><p>We believe beauty is not a standard to meet — it is a feeling to come home to. Our team is here to make your self-care time feel warm, considered and easy.</p><a class="btn btn-primary" href="#book">Find your treatment <span>↗</span></a><div class="story-points"><div class="story-point"><span class="check">✓</span><div><b>Thoughtful care</b><span>Comfort comes first, always.</span></div></div><div class="story-point"><span class="check">✓</span><div><b>Three locations</b><span>Beauty, closer to you.</span></div></div></div></div>
      </div>
    </section>

    <section id="branches">
      <div class="container"><div class="section-head"><div><p class="eyebrow">Come see us</p><h2>Find your<br><span class="script">nearest glow.</span></h2></div><p class="section-lead">Choose the branch that works best for you. We look forward to welcoming you in.</p></div>
        <div class="branches-grid">
          <article class="branch-card"><div class="branch-photo"><img src="{{ asset('images/branch-1.png') }}" alt="Canduman branch banner"></div><div class="branch-body"><span class="branch-tag">Mandaue City</span><h3>Canduman</h3><p>2/F Paradise Bldg., H. Abellana St., Canduman, Mandaue City, Philippines 6014.</p><p><strong>+63 945 377 9343</strong></p><p>Monday to Sunday · 11:00 AM to 8:00 PM</p></div></article>
          <article class="branch-card"><div class="branch-photo"><img src="{{ asset('images/story.png') }}" alt="Pajac branch banner"></div><div class="branch-body"><span class="branch-tag">Lapu-Lapu City</span><h3>Pajac</h3><p>Helenville Apartment, Bankal Road, Barangay Pajac, Lapu-Lapu City, Cebu.</p><p><strong>(032) 326 4014</strong><br>0915 076 6914 · 0956 279 6480</p><p>Hours to be confirmed</p></div></article>
          <article class="branch-card"><div class="branch-photo"><img src="{{ asset('images/branch-3.png') }}" alt="Cebu City branch banner"></div><div class="branch-body"><span class="branch-tag">Cebu City</span><h3>Cebu City</h3><p>8V6R+8VM, Don Gil Street, Cebu City, 6000 Cebu, Philippines.</p><p><strong>0999 325 7150</strong><br>0956 279 6480</p><p>Hours to be confirmed</p></div></article>
        </div>
        <article class="branch-card"><div class="branch-body"><span class="branch-tag">Pusok, Lapu-Lapu City</span><h3>Pusok</h3><p>Address: To be confirmed</p><p><strong>Phone: To be confirmed</strong></p><p>Hours: To be confirmed</p></div></article>
        <div class="map-panel" id="branchMap">
          <div class="map-side">
            <p class="eyebrow">Find us on the map</p>
            <div class="map-tabs" role="group" aria-label="Choose a branch to show on the map"></div>
            <div class="map-details" aria-live="polite"></div>
            <div class="map-actions"></div>
          </div>
          <div class="map-frame"><div class="map-fallback">Loading map…</div></div>
        </div>
      </div>
    </section>

    <!-- SAMPLE CONTENT: placeholder testimonials for the prototype. Replace with real, permitted customer reviews before launch. -->
    <section id="stories" class="testimonials">
      <div class="container">
        <div class="section-head"><div><p class="eyebrow">Customer stories</p><h2>Loved by our<br><span class="script">regulars.</span></h2></div><p class="section-lead">A few words from guests who made a little time for themselves with us.</p></div>
        <div class="testimonial-grid">
          <article class="testimonial"><div class="stars" aria-label="5 out of 5 stars">★★★★★</div><blockquote>“My skin has never felt this clean and calm. The team explained every step of my facial, and I left glowing. I’ve already booked my next visit.”</blockquote><div class="testimonial-person"><span class="testimonial-avatar" aria-hidden="true">AM</span><div><b>Angela M.</b><span>Signature Facial · Canduman</span></div></div></article>
          <article class="testimonial"><div class="stars" aria-label="5 out of 5 stars">★★★★★</div><blockquote>“I came in stressed after a long week and walked out feeling brand new. The body massage was exactly the right pressure. Such a warm, relaxing space.”</blockquote><div class="testimonial-person"><span class="testimonial-avatar alt" aria-hidden="true">RL</span><div><b>Rhea L.</b><span>Relaxing Body Massage · Pajac</span></div></div></article>
          <article class="testimonial"><div class="stars" aria-label="5 out of 5 stars">★★★★★</div><blockquote>“Finally found my go-to salon! My haircut is exactly what I asked for, and the online booking made it so easy to find a time that works.”</blockquote><div class="testimonial-person"><span class="testimonial-avatar" aria-hidden="true">JD</span><div><b>Joanna D.</b><span>Haircut &amp; Blow-dry · Cebu City</span></div></div></article>
          <article class="testimonial"><div class="stars" aria-label="5 out of 5 stars">★★★★★</div><blockquote>“My nails lasted for weeks without chipping. The nail technicians are so patient with details. My friends keep asking where I had them done.”</blockquote><div class="testimonial-person"><span class="testimonial-avatar alt" aria-hidden="true">KS</span><div><b>Kristine S.</b><span>Mani-Pedi · Canduman</span></div></div></article>
          <article class="testimonial"><div class="stars" aria-label="4 out of 5 stars">★★★★☆</div><blockquote>“The hair treatment brought my dry, colored hair back to life. Friendly staff and a really calming atmosphere. I just wish I’d come sooner!”</blockquote><div class="testimonial-person"><span class="testimonial-avatar" aria-hidden="true">MC</span><div><b>Marites C.</b><span>Hair Treatment · Pajac</span></div></div></article>
          <article class="testimonial"><div class="stars" aria-label="5 out of 5 stars">★★★★★</div><blockquote>“I booked a mother-and-daughter day and it was perfect. We both got pampered, and the team made us feel so welcome. Our new monthly treat!”</blockquote><div class="testimonial-person"><span class="testimonial-avatar alt" aria-hidden="true">LG</span><div><b>Liza G.</b><span>Express Glow Facial · Cebu City</span></div></div></article>
        </div>
      </div>
    </section>

    <!-- SAMPLE CONTENT: placeholder community activities for the prototype. Replace with the clinic's real outreach before launch. -->
    <section id="community">
      <div class="container">
        <div class="section-head"><div><p class="eyebrow">Beauty that gives back</p><h2>Caring beyond<br><span class="script">our doors.</span></h2></div><p class="section-lead">We believe everyone deserves to feel cared for. Here’s how our team shares that feeling with the community.</p></div>
        <div class="outreach-grid">
          <article class="outreach-card"><div class="outreach-banner"><span class="outreach-icon" aria-hidden="true">✂</span></div><div class="outreach-body"><span class="outreach-date">Every quarter</span><h3>Free Haircut Day</h3><p>Our stylists set up chairs in partner barangays and give free haircuts to children, seniors and families who need a fresh start.</p><div class="outreach-meta"><span>Mandaue, Lapu-Lapu &amp; Cebu City</span><span>Open to all ages</span></div></div></article>
          <article class="outreach-card"><div class="outreach-banner"><span class="outreach-icon" aria-hidden="true">♡</span></div><div class="outreach-body"><span class="outreach-date">October</span><h3>Pink October Wellness Talks</h3><p>A month of free talks on self-check awareness and self-care, with complimentary mini-treatments for survivors and their caregivers.</p><div class="outreach-meta"><span>All four branches</span><span>Free admission</span></div></div></article>
          <article class="outreach-card"><div class="outreach-banner"><span class="outreach-icon" aria-hidden="true">✦</span></div><div class="outreach-body"><span class="outreach-date">Summer workshops</span><h3>Skin &amp; Grooming Basics for Students</h3><p>Hands-on sessions on simple skincare, sun protection and confident grooming for graduating students getting ready for interviews.</p><div class="outreach-meta"><span>Partner schools</span><span>Limited slots</span></div></div></article>
        </div>
        <div class="impact-strip"><div><strong>500+</strong><span>free haircuts shared with the community</span></div><div><strong>12</strong><span>wellness talks and workshops held</span></div><div><strong>3</strong><span>branches joining every outreach day</span></div></div>
        <p class="sample-note">Sample stories and activities shown for this preview.</p>
      </div>
    </section>

    <section id="book" class="booking">
      <div class="container booking-grid"><div><p class="eyebrow">Reserve your moment</p><h2>Ready when<br><span class="script">you are.</span></h2><p class="section-lead">Appointments are available to registered customers. Sign in or create an account to request a time.</p></div>
        <div>
          
          
          <div class="booking-form"><p class="section-lead">Your account keeps your appointment requests together and lets our team contact you using your saved details.</p><a class="btn btn-primary" href="{{ route('login') }}">Sign in or create account <span>↗</span></a></div>
          
        </div>
      </div>
    </section>
  </main>

  <footer><div class="container"><div class="footer-grid"><div><a class="brand" href="#home"><img src="{{ asset('images/logo.png') }}" alt="EL Glamorous Face and Body Clinic logo"><span class="brand-name">EL Glamorous Face and Body Clinic<small>BRING OUT THE BEAUTY IN YOU.</small></span></a><p style="margin-top:18px;max-width:230px">Face and body clinic, salon and spa in Metro Cebu.</p></div><div><h3>Branches</h3><a href="#branches">Canduman</a><a href="#branches">Pajac</a><a href="#branches">Cebu City</a><a href="#branches">Pusok</a></div><div><h3>Reach us</h3><a href="tel:+639453779343">+63 945 377 9343</a><a href="tel:0323264014">(032) 326 4014</a><a href="tel:09993257150">0999 325 7150</a></div><div><h3>Stay connected</h3><a href="#" aria-label="Facebook">Facebook</a><a href="#" aria-label="Instagram">Instagram · @@elglamteam</a><a class="btn btn-light" href="#book" style="margin-top:10px;font-size:.76rem;padding:10px 15px">Book now ↗</a></div></div><div class="footer-bottom"><span>© 2026 EL Glamorous Face and Body Clinic</span><span>Prototype customer view · Bring out the beauty in you. · <a href="{{ route('login') }}">Team sign in</a></span></div></div></footer>

  <script src="{{ asset('js/landing.js') }}"></script>
</body>
</html>
