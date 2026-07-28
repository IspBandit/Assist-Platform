<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>

<section class="provider-acquisition-hero" aria-labelledby="provider-acquisition-heading">
    <picture class="provider-acquisition-media">
        <img src="<?= e(asset('img/hero-providers.jpg')) ?>" width="1536" height="1024"
             alt="A mobile caravan and RV technician beside a fitted service vehicle"
             loading="eager" fetchpriority="high">
    </picture>
    <div class="provider-acquisition-shade" aria-hidden="true"></div>
    <div class="container provider-acquisition-content">
        <div>
            <p class="experience-kicker">Built for regional service businesses</p>
            <h1 id="provider-acquisition-heading">See the demand.<br><em>Choose the work.</em></h1>
            <p>VanAssist helps caravan and RV specialists build a credible presence, define where they operate and assess customer needs before committing to a job or regional service run.</p>
            <div class="provider-acquisition-actions">
                <a class="btn btn-light btn-lg" href="<?= e(url('for-providers/register')) ?>">Register your business</a>
                <a class="btn btn-glass btn-lg" href="#provider-platform">Explore the platform</a>
            </div>
            <p class="provider-acquisition-note">Registration records your interest. It does not start billing or commit you to accept work.</p>
        </div>
    </div>
</section>

<section class="provider-value-ribbon" aria-label="Provider principles">
    <div class="container">
        <span><strong>No hidden commission</strong>Platform terms remain visible</span>
        <span><strong>You control coverage</strong>Define where and how you work</span>
        <span><strong>No guaranteed-lead claims</strong>Assess genuine registered demand</span>
        <span><strong>One provider identity</strong>Relevant listings across eligible brands</span>
    </div>
</section>

<section class="section provider-platform-section" id="provider-platform" aria-labelledby="provider-platform-heading">
    <div class="container">
        <div class="editorial-heading">
            <p class="experience-kicker dark">A working platform, not another static listing</p>
            <h2 id="provider-platform-heading">Everything needed to run a credible marketplace presence.</h2>
            <p>The provider experience is organised around decisions: what needs attention, where demand exists, and what customers can currently see.</p>
        </div>
        <div class="provider-capability-grid">
            <article class="provider-capability provider-capability-featured">
                <span>01</span><p class="experience-kicker">Opportunity</p><h3>Matched customer requests</h3>
                <p>Review the service, location and urgency before deciding whether to respond.</p>
                <div class="capability-mock"><small>Incoming demand</small><strong>Prioritised by urgency</strong><i>Open request →</i></div>
            </article>
            <article class="provider-capability">
                <span>02</span><p class="experience-kicker dark">Coverage</p><h3>Service areas and runs</h3>
                <p>Describe workshop, mobile, town, region or corridor coverage and organise regional visits.</p>
            </article>
            <article class="provider-capability">
                <span>03</span><p class="experience-kicker dark">Trust</p><h3>Credentials and verification</h3>
                <p>Maintain documents, insurance evidence and licences with honest public status labels.</p>
            </article>
            <article class="provider-capability">
                <span>04</span><p class="experience-kicker dark">Insight</p><h3>Useful activity, clearly described</h3>
                <p>Separate profile views and contact actions from confirmed service outcomes.</p>
            </article>
            <article class="provider-capability">
                <span>05</span><p class="experience-kicker dark">Control</p><h3>Your business information</h3>
                <p>Manage services, availability, public contact details and brand participation from one account.</p>
            </article>
        </div>
    </div>
</section>

<section class="section provider-workflow-section" aria-labelledby="provider-workflow-heading">
    <div class="container provider-workflow-layout">
        <div>
            <p class="experience-kicker">A straightforward joining process</p>
            <h2 id="provider-workflow-heading">Build confidence before visibility.</h2>
            <p>Provider onboarding separates registration, profile completion and evidence review. Nothing is presented as verified until the relevant review has happened.</p>
            <a class="btn btn-light btn-lg" href="<?= e(url('for-providers/register')) ?>">Start with registration</a>
        </div>
        <ol>
            <li><span>01</span><div><h3>Register interest</h3><p>Tell us about the business, services and operating model.</p></div></li>
            <li><span>02</span><div><h3>Complete the business profile</h3><p>Add service areas, public details and the work customers should find you for.</p></div></li>
            <li><span>03</span><div><h3>Submit supporting evidence</h3><p>Provide applicable documents and credentials for review.</p></div></li>
            <li><span>04</span><div><h3>Choose relevant opportunities</h3><p>Assess requests and runs without an obligation to accept unsuitable work.</p></div></li>
        </ol>
    </div>
</section>

<section class="section provider-clarity-section" aria-labelledby="provider-clarity-heading">
    <div class="container provider-clarity-layout">
        <div><p class="experience-kicker dark">Commercial clarity</p><h2 id="provider-clarity-heading">Know what the platform does—and what it does not promise.</h2></div>
        <div class="provider-clarity-grid">
            <article><strong>Demand, not guaranteed revenue</strong><p>Requests and searches indicate customer need. They are not reported as confirmed jobs or income.</p></article>
            <article><strong>Promotion stays labelled</strong><p>Featured visibility is distinct from organic service and location relevance.</p></article>
            <article><strong>Billing remains explicit</strong><p>Registration cannot silently enrol a provider into charging or a paid plan.</p></article>
            <article><strong>One canonical business record</strong><p>Eligible brand participation does not require duplicated profiles or conflicting details.</p></article>
        </div>
    </div>
</section>

<section class="provider-final-cta"><div class="container"><div><p class="experience-kicker">Ready to be easier to find?</p><h2>Start building your provider presence.</h2><p>Register your interest and the team will guide the business through the appropriate onboarding and evidence process.</p></div><a class="btn btn-light btn-lg" href="<?= e(url('for-providers/register')) ?>">Register your business</a></div></section>

<?php $this->endSection(); ?>
