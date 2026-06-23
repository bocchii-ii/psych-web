<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [

            // ── Naruto ────────────────────────────────────────────────────────
            ['body' => 'What is the name of the Nine-Tailed Fox sealed inside Naruto?', 'correct_answer' => 'kurama', 'category' => 'Naruto'],
            ['body' => 'What village is Naruto Uzumaki from?', 'correct_answer' => 'hidden leaf village', 'category' => 'Naruto'],
            ['body' => 'Who is Naruto\'s father?', 'correct_answer' => 'minato namikaze', 'category' => 'Naruto'],
            ['body' => 'What is the name of Naruto\'s signature jutsu?', 'correct_answer' => 'rasengan', 'category' => 'Naruto'],
            ['body' => 'Who is the jonin sensei of Team 7?', 'correct_answer' => 'kakashi hatake', 'category' => 'Naruto'],
            ['body' => 'What clan does Sasuke Uchiha belong to?', 'correct_answer' => 'uchiha clan', 'category' => 'Naruto'],
            ['body' => 'What is the name of Sasuke\'s older brother?', 'correct_answer' => 'itachi uchiha', 'category' => 'Naruto'],

            // ── Dragon Ball ───────────────────────────────────────────────────
            ['body' => 'What is Goku\'s Saiyan birth name?', 'correct_answer' => 'kakarot', 'category' => 'Dragon Ball'],
            ['body' => 'What is the name of Goku\'s signature energy attack?', 'correct_answer' => 'kamehameha', 'category' => 'Dragon Ball'],
            ['body' => 'What race is Goku?', 'correct_answer' => 'saiyan', 'category' => 'Dragon Ball'],
            ['body' => 'Who is Goku\'s first martial arts teacher?', 'correct_answer' => 'master roshi', 'category' => 'Dragon Ball'],
            ['body' => 'What is the name of Goku\'s eldest son?', 'correct_answer' => 'gohan', 'category' => 'Dragon Ball'],
            ['body' => 'What is Vegeta\'s royal title?', 'correct_answer' => 'prince of all saiyans', 'category' => 'Dragon Ball'],
            ['body' => 'How many Dragon Balls are needed to summon Shenron?', 'correct_answer' => '7', 'category' => 'Dragon Ball'],

            // ── Bleach ────────────────────────────────────────────────────────
            ['body' => 'What is the name of Ichigo Kurosaki\'s zanpakuto?', 'correct_answer' => 'zangetsu', 'category' => 'Bleach'],
            ['body' => 'What are Soul Reapers called in Japanese in Bleach?', 'correct_answer' => 'shinigami', 'category' => 'Bleach'],
            ['body' => 'Who is the main antagonist of Bleach?', 'correct_answer' => 'sosuke aizen', 'category' => 'Bleach'],
            ['body' => 'What is the name of the hollow world in Bleach?', 'correct_answer' => 'hueco mundo', 'category' => 'Bleach'],
            ['body' => 'What squad is Rukia Kuchiki originally from?', 'correct_answer' => 'squad 13', 'category' => 'Bleach'],
            ['body' => 'What is Ichigo\'s final Getsuga Tensho transformation called?', 'correct_answer' => 'mugetsu', 'category' => 'Bleach'],

            // ── One Piece ─────────────────────────────────────────────────────
            ['body' => 'What is the name of Luffy\'s pirate crew?', 'correct_answer' => 'straw hat pirates', 'category' => 'One Piece'],
            ['body' => 'What Devil Fruit did Monkey D. Luffy eat?', 'correct_answer' => 'gomu gomu no mi', 'category' => 'One Piece'],
            ['body' => 'What is the name of the legendary treasure everyone seeks in One Piece?', 'correct_answer' => 'one piece', 'category' => 'One Piece'],
            ['body' => 'What is the name of the Straw Hats\' second ship?', 'correct_answer' => 'thousand sunny', 'category' => 'One Piece'],
            ['body' => 'What is the name of Zoro\'s three-sword fighting style?', 'correct_answer' => 'santoryu', 'category' => 'One Piece'],
            ['body' => 'Who is Luffy\'s grandfather?', 'correct_answer' => 'monkey d. garp', 'category' => 'One Piece'],
            ['body' => 'What organization does Nico Robin previously work for?', 'correct_answer' => 'baroque works', 'category' => 'One Piece'],

            // ── Fairy Tail ────────────────────────────────────────────────────
            ['body' => 'Who is the main protagonist of Fairy Tail?', 'correct_answer' => 'natsu dragneel', 'category' => 'Fairy Tail'],
            ['body' => 'What type of magic does Natsu Dragneel use?', 'correct_answer' => 'fire dragon slayer magic', 'category' => 'Fairy Tail'],
            ['body' => 'What is the name of Natsu\'s exceed companion?', 'correct_answer' => 'happy', 'category' => 'Fairy Tail'],
            ['body' => 'Who is the guild master of Fairy Tail?', 'correct_answer' => 'makarov dreyar', 'category' => 'Fairy Tail'],
            ['body' => 'What is the name of Erza Scarlet\'s magic that allows her to swap armor?', 'correct_answer' => 'requip', 'category' => 'Fairy Tail'],
            ['body' => 'What is the name of the ice-make mage in Fairy Tail?', 'correct_answer' => 'gray fullbuster', 'category' => 'Fairy Tail'],

            // ── Attack on Titan ───────────────────────────────────────────────
            ['body' => 'Who is the main protagonist of Attack on Titan?', 'correct_answer' => 'eren yeager', 'category' => 'Attack on Titan'],
            ['body' => 'What is the name of the outermost wall in Attack on Titan?', 'correct_answer' => 'wall maria', 'category' => 'Attack on Titan'],
            ['body' => 'Who is known as humanity\'s strongest soldier in Attack on Titan?', 'correct_answer' => 'levi ackerman', 'category' => 'Attack on Titan'],
            ['body' => 'What is the name of the military branch that ventures beyond the walls?', 'correct_answer' => 'survey corps', 'category' => 'Attack on Titan'],
            ['body' => 'What titan form does Eren first transform into?', 'correct_answer' => 'attack titan', 'category' => 'Attack on Titan'],
            ['body' => 'Who is Eren\'s childhood friend known for exceptional combat skills?', 'correct_answer' => 'mikasa ackerman', 'category' => 'Attack on Titan'],
            ['body' => 'What is the name of the armored titan in Attack on Titan?', 'correct_answer' => 'reiner braun', 'category' => 'Attack on Titan'],

            // ── Another ───────────────────────────────────────────────────────
            ['body' => 'What is the name of the cursed class in the anime Another?', 'correct_answer' => 'class 3-3', 'category' => 'Another'],
            ['body' => 'Who is the mysterious girl with an eye patch in Another?', 'correct_answer' => 'mei misaki', 'category' => 'Another'],
            ['body' => 'In which school does the story of Another take place?', 'correct_answer' => 'yomiyama north middle school', 'category' => 'Another'],

            // ── My Hero Academia ──────────────────────────────────────────────
            ['body' => 'What is the term for superpowers in My Hero Academia?', 'correct_answer' => 'quirk', 'category' => 'My Hero Academia'],
            ['body' => 'Who is the main protagonist of My Hero Academia?', 'correct_answer' => 'izuku midoriya', 'category' => 'My Hero Academia'],
            ['body' => 'What is the name of the hero high school in My Hero Academia?', 'correct_answer' => 'u.a. high school', 'category' => 'My Hero Academia'],
            ['body' => 'What is Izuku Midoriya\'s hero name?', 'correct_answer' => 'deku', 'category' => 'My Hero Academia'],
            ['body' => 'What is the name of All Might\'s quirk passed down to Deku?', 'correct_answer' => 'one for all', 'category' => 'My Hero Academia'],
            ['body' => 'What is the name of Bakugo\'s quirk in My Hero Academia?', 'correct_answer' => 'explosion', 'category' => 'My Hero Academia'],
            ['body' => 'Who is the main villain organization in My Hero Academia?', 'correct_answer' => 'league of villains', 'category' => 'My Hero Academia'],

        ];

        foreach ($questions as $q) {
            Question::firstOrCreate(['body' => $q['body']], $q);
        }

        $this->command->info('Seeded ' . count($questions) . ' questions.');
    }
}
