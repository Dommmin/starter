import {
    Accessibility,
    Globe,
    Lock,
    Palette,
    Rocket,
    ShieldCheck,
    Sparkles,
    Zap,
    type LucideIcon,
} from 'lucide-react';

/**
 * Static map of the closed `HomeIcon` enum to Lucide icons. `Record` over the
 * generated union makes a new backend icon a type error until it is mapped.
 */
export const homeIcons: Record<App.Enums.HomeIcon, LucideIcon> = {
    palette: Palette,
    lock: Lock,
    zap: Zap,
    accessibility: Accessibility,
    'shield-check': ShieldCheck,
    rocket: Rocket,
    sparkles: Sparkles,
    globe: Globe,
};
