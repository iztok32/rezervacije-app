import {
    BedDouble, Bike, Briefcase, Calendar, Camera, Car, Caravan, Dog, Dumbbell, Flower2, Hotel, House,
    Mountain, Music, PawPrint, Sailboat, Scissors, Ship, Sparkles, Stethoscope, Tent, Tractor, TreePine,
    Trophy, Users, Utensils, Volleyball, Waves, type LucideIcon,
} from 'lucide-react';

/** Ikone, ki jih administrator lahko izbere za tip vira. */
export const TYPE_ICONS: Record<string, LucideIcon> = {
    calendar: Calendar,
    'paw-print': PawPrint,
    scissors: Scissors,
    sparkles: Sparkles,
    car: Car,
    caravan: Caravan,
    bike: Bike,
    tent: Tent,
    'tree-pine': TreePine,
    house: House,
    hotel: Hotel,
    'bed-double': BedDouble,
    trophy: Trophy,
    volleyball: Volleyball,
    dumbbell: Dumbbell,
    users: Users,
    waves: Waves,
    sailboat: Sailboat,
    ship: Ship,
    mountain: Mountain,
    music: Music,
    stethoscope: Stethoscope,
    dog: Dog,
    tractor: Tractor,
    utensils: Utensils,
    briefcase: Briefcase,
    camera: Camera,
    flower: Flower2,
};

export function TypeIcon({ name, className }: { name?: string | null; className?: string }) {
    const Icon = (name && TYPE_ICONS[name]) || Calendar;
    return <Icon className={className} />;
}
