<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Fallback voor de SEO-briefing; de key komt primair uit SEO → Instellingen.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),

        /*
         * Het model per soort klus, niet per klasse. Wie het model in de
         * service zelf hardcodeert, krijgt na drie features drie verschillende
         * modellen zonder dat iemand dat besloot — en een model dat er bij het
         * schrijven toevallig stond, blijft daar jaren staan.
         *
         * `reasoning` is het denkwerk: SEO-advies, verbeteracties en volledige
         * landingspagina's uitschrijven. Daar telt de kwaliteit, dus een sterk
         * model.
         *
         * Een project met een vertaallaag zet hier een tweede sleutel bij
         * (`bulk`) voor het sleurwerk — honderden korte strings omzetten, waar
         * niets te bedenken valt en een klein model volstaat. Deze site is
         * eentalig, dus die sleutel staat hier bewust niet: geen dode config.
         */
        'models' => [
            'reasoning' => env('ANTHROPIC_MODEL_REASONING', 'claude-sonnet-5'),
        ],
    ],

    /*
     * Billit (El Pablo BV): voorschotfacturen na een getekende offerte in
     * GoHighLevel. API-sleutel = persoonlijke sleutel uit MyBillit → Mijn
     * profiel → API; PartyID = het bedrijfsnummer in de URL van Instellingen →
     * Mijn bedrijf.
     */
    'billit' => [
        'api_key' => env('BILLIT_API_KEY'),
        'party_id' => env('BILLIT_PARTY_ID'),
        'base_url' => env('BILLIT_BASE_URL', 'https://api.billit.be'),
        'deposit_percentage' => 50,
        'vat_percentage' => 21,
        'payment_days' => 14,
        // Wie een mail krijgt als een voorschotfactuur niet lukt.
        'notify' => env('BILLIT_NOTIFY_EMAIL', 'pieter@dewebgoeroe.be'),
    ],

    // Gedeeld geheim in de header X-Webhook-Secret van de GoHighLevel-webhooks.
    'gohighlevel' => [
        'webhook_secret' => env('GHL_WEBHOOK_SECRET'),

        // Omzetcheck (website-conversiecalculator) → contact in GoHighLevel.
        // Token: Private Integration met scopes contacts.write + contacts.readonly,
        // en voor het eigen boekingsscherm calendars.readonly + calendars/events.write.
        'api_token' => env('GHL_API_TOKEN'),
        'location_id' => env('GHL_LOCATION_ID', 'JJ446huNYhVtsrONFbVO'),
        // Startformulier landingspagina "Speed to lead elektriciens": de tag start
        // de GHL-workflow 1.3 (bevestigingsmail en melding).
        'speed_to_lead' => [
            'tag' => env('GHL_SPEED_TO_LEAD_TAG', 'lp-elektriciens-start'),
            'source' => 'Landingspagina speed to lead elektriciens',
        ],
        'base_url' => env('GHL_BASE_URL', 'https://services.leadconnectorhq.com'),
        // Eigen boekingsscherm (weergave "Eigen kalender"): inleiding in de Google-uitnodiging.
        'booking' => [
            'description' => env('GHL_BOOKING_DESCRIPTION', 'Dit is je gratis gesprek van 30 minuten met Pieter van De WebGoeroe. Je ontvangt ook een bevestiging per e-mail.'),
        ],
        'omzetcheck' => [
            // Tag die de workflow "Omzetcheck ingevuld" start; wordt pas gezet
            // nadat de velden bewaard zijn, zodat de mail het bedrag al kent.
            'trigger_tag' => 'omzetcheck-ingevuld',
            'campaign_tag' => 'campagne-websites',
            // Aangepaste contactvelden in GHL (Instellingen → Aangepaste velden).
            'fields' => [
                'amount' => env('GHL_FIELD_OMZETCHECK_AMOUNT', 'pTNAaeGjLM6rvWfEsPgb'),
                'extra_leads' => env('GHL_FIELD_OMZETCHECK_EXTRA', 'HaBaw5mnNLpjVKmOCa3f'),
                'sector' => env('GHL_FIELD_OMZETCHECK_SECTOR', 'erEowVlDBPvqgg0wKrBR'),
            ],
        ],
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
