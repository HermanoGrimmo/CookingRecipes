<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\Step;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lädt Beispielrezepte in die Datenbank.
 */
#[AsCommand(name: 'app:load-fixtures', description: 'Beispielrezepte laden')]
class LoadFixturesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Bestehende Daten löschen
        $this->em->createQuery('DELETE FROM App\Entity\Step')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Ingredient')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Recipe')->execute();

        // --- Rezept 1: Cashew Hähnchen-Curry ---
        $curry = new Recipe();
        $curry->setTitle('Cashew Hähnchen-Curry')
            ->setDescription('Mit tatkräftiger Unterstützung der Community ist dieses Schmuckstück entstanden. Ein richtig leckeres Hähnchencurry mit knackigen Möhren und Paprika.')
            ->setAuthor('Hannes')
            ->setServings(4)
            ->setPrepTime(30)
            ->setCookTime(0)
            ->setDifficulty('einfach')
            ->setRating('4.2')
            ->setRatingCount(156);

        $curryIngredients = [
            [null, '2', null, 'Möhren'],
            [null, '1', null, 'Paprika, grün'],
            [null, '1', null, 'Mini-Pak Choi'],
            [null, '1', null, 'Bio-Zitrone'],
            [null, '2', null, 'Zwiebeln'],
            [null, '3', null, 'Knoblauchzehen'],
            [null, '2', 'EL', 'Öl'],
            [null, '1', 'TL', 'Kurkuma'],
            [null, '1', 'EL', 'Tomatenmark'],
            [null, '1', 'EL', 'Thai-Currypaste, rot'],
            [null, '400', 'ml', 'Orangensaft'],
            [null, '350', 'ml', 'Kokosmilch'],
            [null, '4', 'EL', 'Sojasauce'],
            [null, null, null, 'Stange Zitronengras'],
            [null, '1', null, 'Kaffir-Limettenblatt'],
            [null, '2', 'TL', 'Zucker'],
            [null, '100', 'g', 'Cashewkerne'],
        ];

        foreach ($curryIngredients as $i => $data) {
            $ingredient = new Ingredient();
            $ingredient->setGroupName($data[0])
                ->setAmount($data[1])
                ->setUnit($data[2])
                ->setName($data[3])
                ->setPosition($i);
            $curry->addIngredient($ingredient);
        }

        $currySteps = [
            ['Gemüse vorbereiten', 'Zwiebeln schälen. Paprika waschen, entkernen und in 2x2 cm große Stücke schneiden. Baby Pak Choi waschen, trocken tupfen, Strunk entfernen und grob schneiden. Gemüse beiseitelegen. Zitrone waschen und Schale abreiben.'],
            ['Curry kochen', 'Möhren schälen und in einem Topf mit Öl glasig anschwitzen. Kurkuma, Tomatenmark und Thai-Currypaste hineingeben und ca. 2 Minuten unter Rühren mit anschwitzen. Mit Orangensaft ablöschen und auf die Hälfte reduzieren. Kokosmilch, Sojasauce, Zitronengras, Kaffirblatt und Zucker zugeben und ca. 15 Minuten bei mittlerer Hitze köcheln lassen.'],
        ];

        foreach ($currySteps as $i => $data) {
            $step = new Step();
            $step->setNumber($i + 1)
                ->setTitle($data[0])
                ->setDescription($data[1]);
            $curry->addStep($step);
        }

        $this->em->persist($curry);

        // --- Rezept 2: Lasagne ---
        $lasagne = new Recipe();
        $lasagne->setTitle('Lasagne')
            ->setDescription('Wie beim Italiener – ein Klassiker mit selbstgemachter Bolognese und cremiger Béchamelsauce.')
            ->setAuthor('hexe107810')
            ->setServings(4)
            ->setPrepTime(45)
            ->setCookTime(60)
            ->setDifficulty('mittel')
            ->setRating('4.7')
            ->setRatingCount(3217);

        $lasagneIngredients = [
            ['Für die Bolognese', null, null, 'Olivenöl'],
            ['Für die Bolognese', '500', 'g', 'Hackfleisch, gemischtes'],
            ['Für die Bolognese', '2', null, 'Knoblauchzehen'],
            ['Für die Bolognese', '2', null, 'Zwiebeln'],
            ['Für die Bolognese', '1', 'Stk.', 'Karotte'],
            ['Für die Bolognese', '1', 'Dose', 'Tomaten, geschälte'],
            ['Für die Bolognese', '1', 'TL', 'Tomatenmark'],
            ['Für die Bolognese', null, null, 'Salz und Pfeffer'],
            ['Für die Bolognese', null, null, 'Oregano'],
            ['Für die Bolognese', null, null, 'Muskat'],
            ['Für die Béchamelsauce', '0.5', 'l', 'Milch'],
            ['Für die Béchamelsauce', '40', 'g', 'Mehl'],
            ['Für die Béchamelsauce', '40', 'g', 'Butter'],
            ['Für die Béchamelsauce', null, null, 'Salz und Pfeffer'],
            ['Für die Béchamelsauce', null, null, 'Muskat'],
            ['Außerdem', null, null, 'Salz'],
            ['Außerdem', '400', 'g', 'Lasagneblätter'],
            ['Außerdem', null, null, 'ca. 150 g Käse, gerieben'],
            ['Außerdem', null, null, 'Butterflöckchen'],
        ];

        foreach ($lasagneIngredients as $i => $data) {
            $ingredient = new Ingredient();
            $ingredient->setGroupName($data[0])
                ->setAmount($data[1])
                ->setUnit($data[2])
                ->setName($data[3])
                ->setPosition($i);
            $lasagne->addIngredient($ingredient);
        }

        $lasagneSteps = [
            ['Ragù Bolognese', 'Öl erhitzen, Hackfleisch darin krümelig anbraten, den Knoblauch dazu pressen. Zwiebeln und Karotte sehr fein schneiden oder raspeln und mit dem geschälten Tomaten und Tomatenmark dazu geben, salzen und pfeffern. Mit den Gewürzen abschmecken. Etwas Wasser dazu geben und mindestens 30 Minuten köcheln lassen.'],
            ['Béchamelsauce', 'Butter in einem kleinen Topf schmelzen und das Mehl mit dem Schneebesen einrühren und verrühren. Die Milch dazugeben und die Sauce unter ständigem Rühren einmal aufkochen lassen. Mit Salz, Pfeffer und Muskatnuss würzen.'],
            ['Zusammenbau der Lasagne', 'In eine gefettete, feuerfeste Form etwas Ragù Bolognese verteilen, eine Schicht Lasagneblätter darauflegen, dann eine Schicht Béchamelsauce, dann wieder Bolognese. Schicht für Schicht so weitermachen. Die letzte Schicht sollte die Béchamelsauce bilden. Dick mit geriebenem Käse bestreuen und mit Butterflöckchen belegen.'],
            ['Backen', 'Die Lasagne im heißen Backofen bei 180°C Umluft ca. 30-40 Minuten backen, bis die Kruste goldbraun ist.'],
        ];

        foreach ($lasagneSteps as $i => $data) {
            $step = new Step();
            $step->setNumber($i + 1)
                ->setTitle($data[0])
                ->setDescription($data[1]);
            $lasagne->addStep($step);
        }

        $this->em->persist($lasagne);

        // --- Rezept 3: Spaghetti Aglio e Olio ---
        $aglio = new Recipe();
        $aglio->setTitle('Spaghetti Aglio e Olio')
            ->setDescription('Der italienische Klassiker mit nur wenigen Zutaten – simpel, schnell und unglaublich lecker.')
            ->setAuthor('Marco')
            ->setServings(2)
            ->setPrepTime(10)
            ->setCookTime(12)
            ->setDifficulty('einfach')
            ->setRating('4.5')
            ->setRatingCount(842);

        $aglioIngredients = [
            [null, '250', 'g', 'Spaghetti'],
            [null, '4', null, 'Knoblauchzehen'],
            [null, '1', null, 'Chilischote, getrocknet'],
            [null, '80', 'ml', 'Olivenöl, extra vergine'],
            [null, null, null, 'Salz'],
            [null, null, null, 'Frische Petersilie'],
            [null, '30', 'g', 'Parmesan'],
        ];

        foreach ($aglioIngredients as $i => $data) {
            $ingredient = new Ingredient();
            $ingredient->setGroupName($data[0])
                ->setAmount($data[1])
                ->setUnit($data[2])
                ->setName($data[3])
                ->setPosition($i);
            $aglio->addIngredient($ingredient);
        }

        $aglioSteps = [
            [null, 'Spaghetti in reichlich Salzwasser nach Packungsanleitung bissfest kochen. Etwa eine Tasse Kochwasser aufheben.'],
            [null, 'Knoblauch in dünne Scheiben schneiden. Chilischote zerbröseln. Olivenöl in einer großen Pfanne bei mittlerer Hitze erwärmen und den Knoblauch darin goldgelb braten – nicht zu dunkel werden lassen!'],
            [null, 'Chili hinzufügen, kurz mitschwenken. Die abgegossenen Spaghetti direkt in die Pfanne geben und mit etwas Nudelwasser vermengen. Mit gehackter Petersilie und frisch geriebenem Parmesan servieren.'],
        ];

        foreach ($aglioSteps as $i => $data) {
            $step = new Step();
            $step->setNumber($i + 1)
                ->setTitle($data[0])
                ->setDescription($data[1]);
            $aglio->addStep($step);
        }

        $this->em->persist($aglio);

        $this->em->flush();

        $io->success('3 Beispielrezepte wurden geladen.');

        return Command::SUCCESS;
    }
}
