<?php

declare(strict_types=1);

namespace DbCms\Core\Database\Seeders;

/**
 * English copy of the starter composition templates.
 *
 * Keys are the exact Spanish strings used by CompositionTemplateSeeder; values are
 * their English version. Strings without an entry (brand names, emails…) stay as is.
 */
final class CompositionTemplateEnglish
{
    /** @var array<string, array{title: string, slug: string}> */
    public const TEMPLATES = [
        'plantilla-hero' => ['title' => 'Hero', 'slug' => 'template-hero'],
        'plantilla-cta' => ['title' => 'Call to action', 'slug' => 'template-cta'],
        'plantilla-caracteristicas' => ['title' => 'Features', 'slug' => 'template-features'],
        'plantilla-faq' => ['title' => 'Frequently asked questions', 'slug' => 'template-faq'],
        'plantilla-planes' => ['title' => 'Pricing plans', 'slug' => 'template-pricing'],
        'plantilla-testimonio' => ['title' => 'Testimonial', 'slug' => 'template-testimonial'],
        'plantilla-galeria' => ['title' => 'Image gallery', 'slug' => 'template-gallery'],
        'plantilla-estadisticas' => ['title' => 'Statistics', 'slug' => 'template-statistics'],
        'plantilla-contacto' => ['title' => 'Contact with map', 'slug' => 'template-contact'],
        'plantilla-video' => ['title' => 'Featured video', 'slug' => 'template-video'],
        'plantilla-pestanas' => ['title' => 'Content tabs', 'slug' => 'template-tabs'],
        'plantilla-carrusel' => ['title' => 'Slide carousel', 'slug' => 'template-carousel'],
        'plantilla-aviso' => ['title' => 'Highlighted notice', 'slug' => 'template-notice'],
        'plantilla-equipo' => ['title' => 'Team', 'slug' => 'template-team'],
    ];

    /** @var array<string, string> */
    public const STRINGS = [
        // Hero
        'Novedad' => 'New',
        'Nuevo: auditoría SEO gratuita para las webs que publiquemos este trimestre.' => 'New: free SEO audit for every website we launch this quarter.',
        'Webs rápidas que convierten visitas en clientes' => 'Fast websites that turn visitors into customers',
        'Diseñamos, redactamos y publicamos tu web en seis semanas. Tú te centras en tu negocio; nosotros, en que te encuentren.' => 'We design, write and launch your website in six weeks. You focus on your business; we make sure people find you.',
        'Pedir presupuesto' => 'Get a quote',
        'Ver planes' => 'See plans',
        'Equipo revisando el diseño de una web en un portátil' => 'Team reviewing a website design on a laptop',
        'Clientes' => 'Clients',
        'Soporte / semana' => 'Support / week',
        'Disponibilidad' => 'Uptime',
        'Mercados' => 'Markets',
        // Call to action
        '¿Hablamos de tu próximo proyecto?' => 'Shall we talk about your next project?',
        'Cuéntanos qué necesitas y en 48 horas te enviamos una propuesta con plazos y presupuesto cerrados.' => 'Tell us what you need and within 48 hours we will send you a proposal with a fixed timeline and budget.',
        'Pedir propuesta' => 'Request a proposal',
        'Hablar con ventas' => 'Talk to sales',
        // Features
        'Todo lo que necesita tu web para crecer' => 'Everything your website needs to grow',
        'Un único equipo para el diseño, el contenido y la tecnología, con resultados que puedes medir.' => 'One team for design, content and technology, with results you can measure.',
        'Diseño a medida' => 'Custom design',
        'Interfaces claras y accesibles, adaptadas a móvil, tableta y escritorio.' => 'Clear, accessible interfaces for mobile, tablet and desktop.',
        'Contenido que posiciona' => 'Content that ranks',
        'Textos revisados por especialistas en SEO y escritos para tu cliente ideal.' => 'Copy reviewed by SEO specialists and written for your ideal customer.',
        'Soporte cercano' => 'Responsive support',
        'Respuesta en menos de 24 horas laborables y mejoras continuas cada mes.' => 'Replies within 24 business hours and continuous improvements every month.',
        // FAQ
        'Preguntas frecuentes' => 'Frequently asked questions',
        'Resolvemos las dudas más habituales antes de empezar. Si no encuentras la tuya, escríbenos.' => 'We answer the most common questions before we start. If yours is not here, write to us.',
        'Contactar soporte' => 'Contact support',
        '¿Cuánto tarda en estar lista mi web?' => 'How long until my website is ready?',
        'Una web corporativa de hasta diez páginas suele publicarse en cuatro a seis semanas.' => 'A corporate website of up to ten pages usually goes live in four to six weeks.',
        '¿Podré editar los contenidos yo mismo?' => 'Will I be able to edit the content myself?',
        'Sí. Te entregamos un panel sencillo y una sesión de formación para tu equipo.' => 'Yes. You get a simple panel and a training session for your team.',
        '¿Qué incluye el mantenimiento?' => 'What does maintenance include?',
        'Actualizaciones de seguridad, copia de seguridad diaria y soporte por correo en horario laboral.' => 'Security updates, daily backups and email support during business hours.',
        '¿Trabajáis con empresas de fuera de España?' => 'Do you work with companies outside Spain?',
        'Sí. Publicamos webs en varios idiomas y mercados desde el mismo panel.' => 'Yes. We publish websites in several languages and markets from the same panel.',
        // Pricing
        'Elige el plan adecuado' => 'Choose the right plan',
        'Precios claros, sin permanencia y con todo lo necesario para empezar a vender en internet.' => 'Clear pricing, no lock-in and everything you need to start selling online.',
        'al mes' => 'per month',
        '1 mercado' => '1 market',
        '2 idiomas' => '2 languages',
        'Hasta 10 páginas' => 'Up to 10 pages',
        'Elegir Starter' => 'Choose Starter',
        '8 mercados' => '8 markets',
        'Módulos ilimitados' => 'Unlimited modules',
        'Soporte prioritario' => 'Priority support',
        'Elegir Professional' => 'Choose Professional',
        'A medida' => 'Custom',
        'Contactar' => 'Contact us',
        // Testimonial
        'Pasamos de semanas a minutos en los despliegues internacionales.' => 'International launches went from weeks to minutes.',
        'Lanzamos la web en cinco países en un mes, y el equipo de marketing ya publica sin depender de desarrollo.' => 'We launched the website in five countries in one month, and the marketing team now publishes without waiting for developers.',
        // Gallery
        'Proyectos recientes' => 'Recent projects',
        'Una muestra de los espacios, productos y equipos con los que hemos trabajado este año.' => 'A selection of the spaces, products and teams we have worked with this year.',
        'Oficina luminosa con zona de trabajo compartida' => 'Bright office with a shared workspace',
        'Espacio de trabajo' => 'Workspace',
        'Producto artesanal sobre una mesa de madera' => 'Handmade product on a wooden table',
        'Detalle de producto' => 'Product detail',
        'Equipo reunido revisando un proyecto' => 'Team meeting to review a project',
        'Equipo en contexto' => 'The team at work',
        // Statistics
        'Resultados que importan' => 'Results that matter',
        'Más de diez años ayudando a empresas a crecer en internet.' => 'More than ten years helping companies grow online.',
        // Contact
        'Hablemos' => 'Let’s talk',
        'Cuéntanos tu proyecto. Respuesta en un día laborable.' => 'Tell us about your project. We reply within one business day.',
        'Lunes a viernes · 9:00–18:00' => 'Monday to Friday · 9:00–18:00',
        'Escribir' => 'Email us',
        'Cómo llegar' => 'Get directions',
        'Oficina de Madrid' => 'Madrid office',
        'Calle de Alcalá 48, 2.º izquierda, 28014 Madrid' => 'Calle de Alcalá 48, 2nd floor left, 28014 Madrid, Spain',
        // Video
        'Míralo en acción' => 'See it in action',
        'En dos minutos te enseñamos a editar textos, cambiar imágenes y publicar una página nueva sin ayuda técnica.' => 'In two minutes we show you how to edit copy, change images and publish a new page without technical help.',
        'Solicitar demo' => 'Request a demo',
        'Demostración del panel' => 'Panel walkthrough',
        'Recorrido de dos minutos por el panel de edición.' => 'A two-minute tour of the editing panel.',
        // Tabs
        'Cómo trabajamos' => 'How we work',
        'Un método probado en más de 300 proyectos, de la primera reunión al lanzamiento.' => 'A method proven in more than 300 projects, from the first meeting to launch.',
        'Fases del proyecto' => 'Project phases',
        'Descubrimiento' => 'Discovery',
        'Entender el objetivo' => 'Understand the goal',
        'Definimos audiencia, mensajería y mercados activos.' => 'We define the audience, messaging and active markets.',
        'Entrevistas' => 'Interviews',
        'Mapa de contenidos' => 'Content map',
        'Prioridades SEO' => 'SEO priorities',
        'Diseño' => 'Design',
        'Prototipo con tu marca' => 'A prototype with your brand',
        'Prototipamos la web con tu identidad y validamos cada página contigo.' => 'We prototype the website with your identity and review every page with you.',
        'Boceto de composición' => 'Layout sketch',
        'Publicación' => 'Launch',
        'Revisar y lanzar' => 'Review and launch',
        'Traducimos, configuramos la analítica y publicamos sin sorpresas.' => 'We translate, set up analytics and go live without surprises.',
        'Ver checklist' => 'See the checklist',
        // Carousel
        'Destacados' => 'Highlights',
        'Tres proyectos recientes que muestran cómo trabajamos.' => 'Three recent projects that show how we work.',
        'Carrusel de destacados' => 'Highlights carousel',
        'Uno' => 'One',
        'Dos' => 'Two',
        'Tres' => 'Three',
        'Clínica Aurora: un 38 % más de citas' => 'Aurora Clinic: 38% more appointments',
        'Nueva web con reserva online y recordatorios automáticos por correo.' => 'A new website with online booking and automatic email reminders.',
        'Recepción luminosa de una clínica' => 'Bright clinic reception area',
        'Saber más' => 'Learn more',
        'Bodegas Lumbre: tienda en tres idiomas' => 'Lumbre Winery: a shop in three languages',
        'Catálogo con envíos a toda la Unión Europea y pago seguro.' => 'A catalogue shipping across the European Union with secure payment.',
        'Botellas de vino sobre una barrica' => 'Wine bottles on a barrel',
        'Estudio Norte: portfolio en minutos' => 'Norte Studio: a portfolio in minutes',
        'El propio equipo publica sus proyectos nuevos desde el panel.' => 'The team publishes its new projects from the panel on its own.',
        // Notice
        'Aviso' => 'Notice',
        'Del 23 de diciembre al 6 de enero atendemos solo por correo. Los pedidos se enviarán a partir del 7 de enero.' => 'From 23 December to 6 January we only answer by email. Orders will ship from 7 January.',
        'Ver horarios' => 'See opening hours',
        // Team
        'Quiénes lo hacen posible' => 'The people behind it',
        'Un equipo pequeño y sénior que se implica en cada proyecto de principio a fin.' => 'A small, senior team involved in every project from start to finish.',
        "**Producto**\n\nCoordina cada proyecto y es tu contacto directo desde el primer día." => "**Product**\n\nCoordinates every project and is your direct contact from day one.",
        "**Ingeniería**\n\nSe asegura de que tu web cargue rápido, sea segura y crezca contigo." => "**Engineering**\n\nMakes sure your website loads fast, stays secure and grows with you.",
        "**Diseño**\n\nConvierte tu marca en interfaces claras, accesibles y memorables." => "**Design**\n\nTurns your brand into clear, accessible and memorable interfaces.",
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function translate(array $payload): array
    {
        array_walk_recursive($payload, static function (mixed &$value): void {
            if (is_string($value) && isset(self::STRINGS[$value])) {
                $value = self::STRINGS[$value];
            }
        });

        return $payload;
    }
}
