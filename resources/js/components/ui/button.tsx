import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ' +
    'ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 ' +
    'focus-visible:ring-zinc-400 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default:   'bg-zinc-900 text-white hover:bg-zinc-800',
                outline:   'border border-zinc-200 bg-white hover:bg-zinc-50 text-zinc-900',
                ghost:     'text-zinc-700 hover:bg-zinc-100',
                secondary: 'bg-zinc-100 text-zinc-900 hover:bg-zinc-200',
            },
            size: {
                default: 'h-9 px-4 py-2',
                sm:      'h-8 rounded-md px-3',
                xs:      'h-7 rounded-md px-2 text-xs',
                icon:    'h-9 w-9',
            },
        },
        defaultVariants: { variant: 'default', size: 'default' },
    }
);

export interface ButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

export function Button({ className, variant, size, asChild, ...props }: ButtonProps) {
    const Comp = asChild ? Slot : 'button';
    return <Comp className={cn(buttonVariants({ variant, size, className }))} {...props} />;
}
