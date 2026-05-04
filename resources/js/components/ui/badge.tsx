import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
    {
        variants: {
            variant: {
                default:  'bg-zinc-100 text-zinc-900 ring-zinc-200',
                personal: 'bg-blue-50 text-blue-700 ring-blue-200',
                savvior:  'bg-purple-50 text-purple-700 ring-purple-200',
                muted:    'bg-zinc-50 text-zinc-500 ring-zinc-200',
                active:   'bg-green-50 text-green-700 ring-green-200',
                paused:   'bg-amber-50 text-amber-700 ring-amber-200',
                done:     'bg-zinc-100 text-zinc-500 ring-zinc-200',
            },
        },
        defaultVariants: { variant: 'default' },
    }
);

export interface BadgeProps
    extends React.HTMLAttributes<HTMLSpanElement>,
        VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}
