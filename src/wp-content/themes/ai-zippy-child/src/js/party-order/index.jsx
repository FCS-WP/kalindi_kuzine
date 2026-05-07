import { createRoot } from '@wordpress/element';
import PartyOrder from './PartyOrder.jsx';

const container = document.getElementById('ai-zippy-party-order');
if (container) {
    const root = createRoot(container);
    root.render(
        <PartyOrder 
            limit={parseInt(container.dataset.limit || 8)} 
            columns={parseInt(container.dataset.columns || 3)}
        />
    );
}
