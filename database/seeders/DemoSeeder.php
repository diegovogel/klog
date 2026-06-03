<?php

namespace Database\Seeders;

use App\Enums\MediaType;
use App\Enums\MimeType;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\Memory;
use App\Models\User;
use App\Services\ScreenshotFeatureService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds a believable but entirely fictional family memory log for the public
 * demo. NOT wired into DatabaseSeeder — it is invoked only by `demo:reset`.
 *
 * Photos under database/seeders/demo-assets/ are generic, people-free stock
 * images from Lorem Picsum (https://picsum.photos), which serves Unsplash
 * photos under the Unsplash License (free for commercial use, no attribution
 * required). Captions are written to fit each image.
 */
class DemoSeeder extends Seeder
{
    private const ASSET_DISK = 'local';

    public function run(): void
    {
        // migrate:fresh wipes app_settings and a missing screenshots_enabled
        // flag defaults to "on", so pin it off here. The demo has no UI path to
        // change it back, and we don't want the screenshot schedule capturing
        // the seeded clipping URLs on a public instance.
        app(ScreenshotFeatureService::class)->setEnabled(false);

        $sam = User::create([
            'name' => 'Sam Rivera',
            'email' => config('klog.demo_email'),
            'password' => Hash::make(config('klog.demo_password')),
            'role' => UserRole::ADMIN,
        ]);

        // Keep the fictional co-parent's email distinct from the (configurable)
        // demo email so a custom DEMO_EMAIL can't collide with this fixture and
        // trip the unique constraint, which would fail demo:reset mid-seed.
        $coParentEmail = config('klog.demo_email') === 'alex@example.com'
            ? 'alex.rivera@example.com'
            : 'alex@example.com';

        $alex = User::create([
            'name' => 'Alex Rivera',
            'email' => $coParentEmail,
            'password' => Hash::make(Str::random(32)),
            'role' => UserRole::MEMBER,
        ]);

        $mia = Child::create(['name' => 'Mia']);
        $theo = Child::create(['name' => 'Theo']);

        $authors = ['sam' => $sam, 'alex' => $alex];
        $kids = ['Mia' => $mia, 'Theo' => $theo];

        foreach ($this->memories() as $spec) {
            $date = Carbon::now()->subDays($spec['days'])->setTime(19, 30);

            $memory = Memory::create([
                'user_id' => $authors[$spec['author']]->id,
                'title' => $spec['title'],
                'content' => $this->paragraphs($spec['body'] ?? []),
                'memory_date' => $date,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            if (! empty($spec['children'])) {
                $memory->children()->attach(
                    collect($spec['children'])->map(fn (string $name): int => $kids[$name]->id)
                );
            }

            if (! empty($spec['tags'])) {
                $memory->attachTagNames($spec['tags']);
            }

            if (! empty($spec['photo'])) {
                $this->attachPhoto($memory, $spec['photo'], $date);
            }

            if (! empty($spec['clipping'])) {
                $memory->webClippings()->create($spec['clipping']);
            }

            $memory->reindexSearch();
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function paragraphs(array $lines): ?string
    {
        if ($lines === []) {
            return null;
        }

        return collect($lines)
            ->map(fn (string $line): string => '<p>'.e($line).'</p>')
            ->implode('');
    }

    private function attachPhoto(Memory $memory, string $file, Carbon $date): void
    {
        $source = database_path('seeders/demo-assets/'.$file);
        $uuid = Str::uuid()->toString();
        $directory = sprintf('uploads/%s/%s', $date->format('Y'), $date->format('m'));
        $path = $directory.'/'.$uuid.'.jpg';

        Storage::disk(self::ASSET_DISK)->put($path, file_get_contents($source));

        $memory->media()->create([
            'filename' => $uuid,
            'original_filename' => $file,
            'mime_type' => MimeType::JPEG->value,
            'captured_at' => $date,
            'size' => filesize($source),
            'disk' => self::ASSET_DISK,
            'path' => $path,
            'type' => MediaType::IMAGE->value,
            'metadata' => null,
            'order' => 0,
        ]);
    }

    /**
     * Curated, fictional memory log. `days` is days before "today" so the feed
     * spans roughly two years and stays fresh relative to whenever it's reseeded.
     *
     * @return array<int, array<string, mixed>>
     */
    private function memories(): array
    {
        return [
            [
                'days' => 4,
                'author' => 'sam',
                'title' => 'Creek walk in the woods',
                'body' => [
                    'We followed the trail down to the creek and Theo insisted on turning over every rock to look for "river bugs."',
                    'Mia found a smooth grey stone she has decided is her lucky one. It is now living in my coat pocket.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['outdoors', 'weekend'],
                'photo' => 'forest-stream.jpg',
            ],
            [
                'days' => 9,
                'author' => 'alex',
                'title' => 'Pancake Sunday',
                'body' => [
                    'Theo cracked the eggs (mostly into the bowl) and we made a stack taller than was strictly sensible.',
                    'He has requested that we make this a forever rule.',
                ],
                'children' => ['Theo'],
                'tags' => ['food', 'home'],
            ],
            [
                'days' => 15,
                'author' => 'sam',
                'title' => 'Slow Saturday morning',
                'body' => [
                    'Coffee for us, warm milk for the kids, and a long stretch of nobody needing to be anywhere.',
                    'Mia read three chapters out loud while Theo built and demolished the same tower a dozen times.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['home', 'morning'],
                'photo' => 'kitchen-morning.jpg',
            ],
            [
                'days' => 22,
                'author' => 'sam',
                'title' => 'Mia\'s flowers bloomed',
                'body' => [
                    'The seeds she planted back in spring finally opened. She checked them every single morning, so this felt earned.',
                ],
                'children' => ['Mia'],
                'tags' => ['garden', 'milestones'],
                'photo' => 'orange-flowers.jpg',
            ],
            [
                'days' => 27,
                'author' => 'alex',
                'title' => 'Library haul',
                'body' => [
                    'Came home with a tower of picture books and one enormous atlas Theo can barely lift but refuses to put down.',
                ],
                'children' => ['Theo'],
                'tags' => ['books'],
                'clipping' => [
                    'url' => 'https://www.readbrightly.com/best-picture-books/',
                    'title' => 'The Best Picture Books for Every Age',
                    'content' => '<h2>Picture books we keep coming back to</h2><p>A roundup of read-aloud favourites for toddlers through early readers, sorted by age and theme.</p><ul><li>Bedtime and bath-time stories</li><li>Counting and first words</li><li>Big-feelings books for little kids</li></ul>',
                ],
            ],
            [
                'days' => 33,
                'author' => 'sam',
                'title' => 'Tea and a wobbly tooth',
                'body' => [
                    'Mia announced her first wobbly tooth over afternoon tea, with the gravity of someone reporting breaking news.',
                    'We have been assured the tooth fairy definitely takes requests.',
                ],
                'children' => ['Mia'],
                'tags' => ['milestones'],
                'photo' => 'afternoon-tea.jpg',
            ],
            [
                'days' => 41,
                'author' => 'sam',
                'title' => 'Pelicans on the pier',
                'body' => [
                    'A whole row of them, completely unbothered by the kids tiptoeing closer for a better look.',
                    'Theo has decided pelicans are the best bird and will hear no argument.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['outdoors', 'animals', 'seaside'],
                'photo' => 'pelicans-pier.jpg',
            ],
            [
                'days' => 52,
                'author' => 'alex',
                'title' => 'First lost tooth',
                'body' => [
                    'It finally came out during dinner. Much excitement, slightly less dinner.',
                ],
                'children' => ['Mia'],
                'tags' => ['milestones'],
            ],
            [
                'days' => 63,
                'author' => 'sam',
                'title' => 'The bear at the wildlife park',
                'body' => [
                    'We watched him for ages from the viewing platform. He looked directly at us once and both kids went completely silent.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['animals', 'day-trip'],
                'photo' => 'brown-bear.jpg',
            ],
            [
                'days' => 74,
                'author' => 'sam',
                'title' => 'Balloon festival',
                'body' => [
                    'Up before sunrise to watch them inflate. Worth every minute of the early alarm.',
                    'Theo wants to know how soon he is allowed to ride in one.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['day-trip', 'outdoors'],
                'photo' => 'hot-air-balloon.jpg',
            ],
            [
                'days' => 88,
                'author' => 'alex',
                'title' => 'Rainy day fort',
                'body' => [
                    'Every cushion in the house was conscripted. The fort had a no-grown-ups rule that lasted until snacks were required.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['home', 'indoors'],
            ],
            [
                'days' => 99,
                'author' => 'sam',
                'title' => 'The pond at the botanical garden',
                'body' => [
                    'Mia counted eleven lily pads and one very smug frog. Theo tried to count the koi and gave up at "lots."',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['outdoors', 'day-trip'],
                'photo' => 'water-lily.jpg',
            ],
            [
                'days' => 112,
                'author' => 'sam',
                'title' => 'First real hike',
                'body' => [
                    'Both kids made it to the lookout on their own legs, which we are choosing to remember as the headline.',
                    'Snacks were deployed strategically. Morale held.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['outdoors', 'milestones'],
                'photo' => 'mountain-trail.jpg',
            ],
            [
                'days' => 126,
                'author' => 'alex',
                'title' => 'Theo\'s drawing phase',
                'body' => [
                    'Everything is dinosaurs right now. The fridge has reached capacity. We are rotating exhibits.',
                ],
                'children' => ['Theo'],
                'tags' => ['art'],
            ],
            [
                'days' => 140,
                'author' => 'sam',
                'title' => 'Blossoms finally out',
                'body' => [
                    'The whole street turned pink overnight. We took the long way to school just to walk under them.',
                ],
                'children' => ['Mia'],
                'tags' => ['outdoors', 'spring'],
                'photo' => 'cherry-blossoms.jpg',
            ],
            [
                'days' => 158,
                'author' => 'sam',
                'title' => 'Soup recipe to keep',
                'body' => [
                    'Both kids actually ate it, which makes this a recipe worth bookmarking forever.',
                ],
                'tags' => ['food', 'recipes'],
                'clipping' => [
                    'url' => 'https://www.bbcgoodfood.com/recipes/collection/soup-recipes',
                    'title' => 'Cozy Vegetable Soup',
                    'content' => '<h2>Cozy Vegetable Soup</h2><p>A forgiving one-pot soup that scales easily and reheats well.</p><p>Sweat onion, carrot and celery, add stock and whatever vegetables need using up, simmer until tender, finish with a handful of pasta.</p>',
                ],
            ],
            [
                'days' => 175,
                'author' => 'alex',
                'title' => 'Up on the cliffs',
                'body' => [
                    'Big wind, bigger views. We held onto hats and the kids held onto us.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['seaside', 'outdoors'],
                'photo' => 'coast-cliffs.jpg',
            ],
            [
                'days' => 196,
                'author' => 'sam',
                'title' => 'Theo can ride without training wheels',
                'body' => [
                    'Three wobbly metres, then ten, then off down the path with a grin I will not forget.',
                ],
                'children' => ['Theo'],
                'tags' => ['milestones'],
            ],
            [
                'days' => 214,
                'author' => 'sam',
                'title' => 'Evening field, golden hour',
                'body' => [
                    'We let the kids run themselves out before bed. The light was absurd, in the best way.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['outdoors', 'evening'],
                'photo' => 'golden-field.jpg',
            ],
            [
                'days' => 235,
                'author' => 'alex',
                'title' => 'Mia\'s first sleepover',
                'body' => [
                    'She packed for a week, stayed one night, and came home full of stories and slightly short on sleep.',
                ],
                'children' => ['Mia'],
                'tags' => ['milestones'],
            ],
            [
                'days' => 258,
                'author' => 'sam',
                'title' => 'The cabin weekend',
                'body' => [
                    'No wifi, one ancient bicycle, and a porch the kids claimed as headquarters.',
                    'We will be coming back here.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['trip', 'outdoors'],
                'photo' => 'cabin-bike.jpg',
            ],
            [
                'days' => 286,
                'author' => 'sam',
                'title' => 'The white village',
                'body' => [
                    'Steep little streets, blue doors, and ice cream that did not survive the walk back to the room.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['trip', 'travel'],
                'photo' => 'seaside-village.jpg',
            ],
            [
                'days' => 312,
                'author' => 'alex',
                'title' => 'Planning the trip',
                'body' => [
                    'Spent the evening reading up on things to do with kids. Saving this list for later.',
                ],
                'tags' => ['travel', 'planning'],
                'clipping' => [
                    'url' => 'https://www.lonelyplanet.com/articles/traveling-with-kids',
                    'title' => 'Tips for Traveling With Young Kids',
                    'content' => '<h2>Traveling with young kids</h2><p>Practical advice on pacing, packing light, and keeping small travellers happy on long days.</p><ul><li>Build in downtime</li><li>Snacks are infrastructure</li><li>Let kids carry their own small bag</li></ul>',
                ],
            ],
            [
                'days' => 348,
                'author' => 'sam',
                'title' => 'Theo started preschool',
                'body' => [
                    'Walked in without looking back. I, on the other hand, looked back several times.',
                ],
                'children' => ['Theo'],
                'tags' => ['milestones', 'school'],
            ],
            [
                'days' => 392,
                'author' => 'sam',
                'title' => 'Snow day',
                'body' => [
                    'School called it off, so we did too. A snowman was built, named, and mourned the next morning.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['winter', 'home'],
            ],
            [
                'days' => 451,
                'author' => 'alex',
                'title' => 'Mia\'s first day of school',
                'body' => [
                    'New shoes, enormous backpack, slightly nervous smile. She found her name on the coat hook and that was that.',
                ],
                'children' => ['Mia'],
                'tags' => ['milestones', 'school'],
            ],
            [
                'days' => 523,
                'author' => 'sam',
                'title' => 'Beach day, take one',
                'body' => [
                    'Theo was deeply suspicious of the sand and then refused to leave it.',
                ],
                'children' => ['Mia', 'Theo'],
                'tags' => ['seaside', 'summer'],
            ],
            [
                'days' => 604,
                'author' => 'sam',
                'title' => 'Theo says his first sentence',
                'body' => [
                    'More toast please. Polite and to the point. We are very proud.',
                ],
                'children' => ['Theo'],
                'tags' => ['milestones', 'firsts'],
            ],
            [
                'days' => 712,
                'author' => 'alex',
                'title' => 'Mia loses her first race and wins the day',
                'body' => [
                    'Came fourth, was thrilled regardless, demanded a rematch and a popsicle.',
                ],
                'children' => ['Mia'],
                'tags' => ['sports'],
            ],
        ];
    }
}
