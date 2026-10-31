@extends('layouts.app')
@php
    // Policy content must be reviewed by legal counsel before launch.
    $policies = [
        'terms' => [
            'title' => 'Terms & Conditions',
        ],
        'privacy' => [
            'title' => 'Privacy Policy',
            'items' => [
                ['What we collect', 'Contact details, passport information needed to book, payment tokens and the preferences you share with us.'],
                ['How we use it', 'To process bookings, provide support, send requested updates and meet legal requirements. We never sell personal data.'],
                ['Sharing', 'We share only what suppliers need to deliver your trip: airlines, hotels and cruise lines, under data-protection agreements.'],
                ['Your rights', 'You can request access, correction or deletion at any time at privacy@travelwithyou.com.'],
            ],
        ],
    ];
    $currentPolicy = $policies[$policy];
@endphp
@section('title', $currentPolicy['title'].' — TravelWithYou')
@section('content')
<x-page-head kicker="Legal" :title="$currentPolicy['title']" :lead="'Last updated '.\Illuminate\Support\Carbon::parse(config('company.legal_updated'))->format('j F Y').'.'" />
@if($policy === 'privacy')
    <main class="wrap policy-sections privacy-sections">
        <p class="privacy-intro"><strong>Your privacy is important to us. Here's how we handle your data.</strong></p>
        <article>
            <h2><span>1.</span>Introduction</h2>
            <p>Welcome to Travel With You Sdn Bhd. We value your privacy and are committed to protecting your personal information. This Privacy Policy outlines the types of data we collect, how we use it, and your rights regarding your information.</p>
        </article>
        <article>
            <h2><span>2.</span>Information We Collect</h2>
            <p>We collect information to provide better services to all our clients. This may include:</p>
            <ul>
                <li><strong>Personal Information:</strong> Name, email address, phone number, passport details, and other contact information.</li>
                <li><strong>Payment Information:</strong> Credit/debit card details or bank information used for processing transactions.</li>
                <li><strong>Travel Information:</strong> Details about your trip, such as flight, hotel, and itinerary preferences.</li>
            </ul>
        </article>
        <article>
            <h2><span>3.</span>How We Use Your Information</h2>
            <p>We use the collected information for the following purposes:</p>
            <ul>
                <li><strong>To Provide Services:</strong> Processing bookings, issuing tickets, arranging accommodations, and other travel-related services.</li>
                <li><strong>Customer Support:</strong> Responding to inquiries, complaints, and providing assistance related to bookings or services.</li>
                <li><strong>Payment Processing:</strong> Handling payment transactions and refunds.</li>
                <li><strong>Marketing and Promotions:</strong> Sending promotional offers, newsletters, or travel tips (you can opt out at any time).</li>
                <li><strong>Legal and Compliance:</strong> Ensuring compliance with laws, regulations, and internal policies.</li>
            </ul>
        </article>
        <article>
            <h2><span>4.</span>Sharing Your Information</h2>
            <p>We may share your information with third parties under the following circumstances:</p>
            <ul>
                <li><strong>Service Providers:</strong> Trusted partners such as airlines, hotels, and tour operators to fulfill your travel arrangements.</li>
                <li><strong>Payment Processors:</strong> Third-party payment gateways for secure transaction processing.</li>
                <li><strong>Legal Obligations:</strong> If required by law, regulation, or legal process (e.g., court order), we may disclose your information.</li>
            </ul>
        </article>
        <article>
            <h2><span>5.</span>Data Security</h2>
            <p>We take data security seriously and implement appropriate technical and organizational measures to protect your information. However, please note that no method of transmission over the Internet or electronic storage is 100% secure.</p>
        </article>
        <article>
            <h2><span>6.</span>Your Rights</h2>
            <p>Depending on your location, you may have rights under applicable data protection laws, including:</p>
            <ul>
                <li><strong>Access:</strong> Request access to your personal information.</li>
                <li><strong>Correction:</strong> Request corrections to any inaccurate or incomplete information.</li>
                <li><strong>Deletion:</strong> Request deletion of your data, subject to legal and contractual obligations.</li>
                <li><strong>Opt-Out:</strong> Choose not to receive marketing communications.</li>
            </ul>
            <p>To exercise these rights, please contact us using the information provided below.</p>
        </article>
        <article>
            <h2><span>7.</span>Third-Party Links</h2>
            <p>Our website may contain links to other sites. We are not responsible for the privacy practices or content of these third-party websites. We encourage you to review the privacy policies of any third-party sites you visit.</p>
        </article>
        <article>
            <h2><span>8.</span>Updates to This Privacy Policy</h2>
            <p>We may update this Privacy Policy periodically to reflect changes in our practices or legal requirements. Any updates will be posted on this page, and significant changes will be communicated directly to users.</p>
        </article>
        <article>
            <h2><span>9.</span>Contact Us</h2>
            <p>If you have questions about this Privacy Policy or wish to exercise your rights regarding your personal data, please contact us at:</p>
            <address class="privacy-contact">
                <strong>Travel With You Sdn Bhd</strong>
                <span>Address: Stellar Suites SS-10-02, Jalan Puteri 4/7, Bandar Puteri 47100 Puchong, Selangor</span>
                <span>Email: enquiry@travelwithyou.my</span>
                <span>Tel: +60 13-2883003</span>
            </address>
        </article>
    </main>
@elseif($policy === 'terms')
    <main class="wrap policy-sections terms-sections">
        <header class="terms-document-title">
            <h2>TOURISM INDUSTRY ACT 1992</h2>
            <h2>TOURISM INDUSTRY (TOUR OPERATING BUSINESS AND TRAVEL AGENCY BUSINESS) REGULATIONS 1992</h2>
        </header>
        <article>
            <h2><span>1.</span>Tour Deposit</h2>
            <p>A maximum deposit of <strong>25% of the tour fare per person</strong> must be paid as reservation fee. The balance or full payment must be made:</p>
            <ul>
                <li>Within 14 days before the date of departure for <strong>Free Independent Traveller (FIT)</strong> tour packages.</li>
                <li>Within 21 days before the date of departure for group tour packages.</li>
            </ul>
            <p>Failure to comply may result in cancellation of the reservation and forfeiture of the deposit.</p>
        </article>
        <article>
            <h2><span>2.</span>Amendment Charges</h2>
            <h3>2.2 For Group Tour packages</h3>
            <ol>
                <li>Upon confirmation of the tour, no change can be made within <strong>14 days before the date of departure.</strong></li>
            </ol>
        </article>
        <article id="terms-cancellation">
            <h2><span>3.</span>Cancellation Charges (By Tour Member)</h2>
            <h3>3.1 For FIT Tour Packages</h3>
            <ol>
                <li>Cancellation must be made in <strong>writting.</strong></li>
                <li>Charges for cancellation:
                    <ul>
                        <li><strong>30 days or more before departure:</strong> Administrative fee of RM50.00 or 10% of the tour deposit (whichever is lower).</li>
                        <li><strong>29 days or less before departure:</strong>
                            <ul>
                                <li>15-29 days: <strong>50% of the tour deposit</strong></li>
                                <li>8-14 days: <strong>20% of the tour fare</strong></li>
                                <li>3-7 days: 40% of the tour fare</li>
                                <li>2 days or less: 100% of the tour fare</li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ol>
            <h3>3.2 For Group Tour Packages</h3>
            <ol>
                <li>Cancellation must be made in <strong>writting.</strong></li>
                <li>Charges for cancellation:
                    <ul>
                        <li><strong>45 days or more before departure:</strong> Administrative fee of RM30.00 or 2% of the tour fare (whichever is higher).</li>
                        <li><strong>44 days or less:</strong>
                            <ul>
                                <li>22 days - 44 days: Forfeiture of tour deposit.</li>
                                <li>15 days - 21 days: 35% of the tour fare.</li>
                                <li>8-14 days: 50% of the tour fare.</li>
                                <li>3-7 days: 75% of the tour fare.</li>
                                <li>2 days or less: 100% of the tour fare.</li>
                            </ul>
                        </li>
                    </ul>
                </li>
            </ol>
            <p><strong>Note:</strong> Airline cancellation policies on promotional fares apply.</p>
        </article>
        <article>
            <h2><span>4.</span>Cancellation (By Company)</h2>
            <p>The company reserves the right to cancel tours due to unforeseen circumstances such as:</p>
            <ul>
                <li>Act of God, war, strikes, or govermnent orders.</li>
            </ul>
            <p>If cancellation occurs:</p>
            <ul>
                <li>Alternative tours will be recommended. If not acceptable, all payment, minus administrative fees, will be refunded.</li>
                <li>Compensation will be paid for cancellation due to insufficient passengers:
                    <ul>
                        <li>8-14 days before departure: Full refund</li>
                        <li>1-7 days before departure: Full refund + RM75.00 per person.</li>
                        <li>On the date of departure: Full refund + RM100.00 per person.</li>
                    </ul>
                </li>
            </ul>
        </article>
        <article>
            <h2><span>5.</span>Travel Documents</h2>
            <h3>1. Tour Member Responsibilities:</h3>
            <ul>
                <li>Ensure passports are valid for <strong>at least 6 months</strong> from the return date.</li>
                <li>Obtain necessary visas, vaccinations, and health certificates.</li>
            </ul>
            <h3>2. Company Responsibilities:</h3>
            <ul>
                <li>Advise on required documents.</li>
                <li>Assist with visa applications (approval is not guaranteed.)</li>
            </ul>
            <h3>3. Fees:</h3>
            <ul>
                <li>All visa fees and services charges are borne by the tour members.</li>
            </ul>
            <h3>4. Rejection or Refusal:</h3>
            <ul>
                <li>No refunds are provided for visa rejections or refusal of entry by immigration.</li>
            </ul>
        </article>
        <article>
            <h2><span>6.</span>Tour Information and Prices</h2>
            <ol>
                <li>Prices are subject to changes due to fluctuations in airfares, taxes, or currency exchange rates.</li>
                <li>Included in the tour fare:
                    <ul>
                        <li>Return air ticket (excluding taxes).</li>
                        <li>Hotel accommodation (twin, single, or triple sharing).</li>
                        <li>Meals, sightseeing, and transfers as per itinerary.</li>
                    </ul>
                </li>
                <li>Not included in the tour fare:
                    <ul>
                        <li>Laundry, optional excursions, and personal items.</li>
                    </ul>
                </li>
            </ol>
        </article>
        <article>
            <h2><span>7.</span>Refund for Unused Services</h2>
            <p>No refund will be provided for unused services, such as airfare, accomodation, meals, or tours, due to personal reasons.</p>
        </article>
        <article>
            <h2><span>8.</span>Baggage</h2>
            <p>Free airline baggage allowances differ by airline and airport. Passengers are advised to confirm these setails and purchase adequate insurance.</p>
        </article>
        <article>
            <h2><span>9.</span>Possible Changes</h2>
            <p>The company may alter itineraries due to local conditions or airline schedules. Alternative arrangements will be made when necessary.</p>
        </article>
        <article>
            <h2><span>10.</span>Right to Reject</h2>
            <p>The company reserves the right to:</p>
            <ul>
                <li>Cancel any reservation if deemed detrimental to the group.</li>
                <li>Refund payments, minus administrative and cancellation fees, for services already utilized.</li>
            </ul>
        </article>
        <article>
            <h2><span>11.</span>Company Responsibilities</h2>
            <p>The company is responsible for providing services as stated in brochures. Compensation will be paid for unsatisfactory services as determined by the Commissioner of Tourism.</p>
        </article>
        <article>
            <h2><span>12.</span>Travel Insurance</h2>
            <p>All tour members are encouraged to purchase travel insurance to cover unforeseen circumstances such as illness, cancellations, or lost baggage.</p>
        </article>
    </main>
@else
    <section class="wrap policy-sections">
        @foreach($currentPolicy['items'] as [$heading, $body])
            <article>
                <h2><span>{{ $loop->iteration }}.</span>{{ $heading }}</h2>
                <p>{{ $body }}</p>
            </article>
        @endforeach
    </section>
@endif
@endsection
