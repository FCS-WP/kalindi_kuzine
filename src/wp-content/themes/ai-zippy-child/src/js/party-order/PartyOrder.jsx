import { useState, useEffect } from 'react';
import { addToCart } from '@/js/modules/cart-api';
import { updateMiniCart, showToast } from '@/js/modules/cart-ui';
import './style.scss';

// Reuse API base from parent theme
const BASE = "/wp-json/ai-zippy/v1";

export default function PartyOrder({ limit, columns }) {
    const [products, setProducts] = useState([]);
    const [categories, setCategories] = useState([]);
    const [activeCategory, setActiveCategory] = useState('party-order');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showConfirm, setShowConfirm] = useState(false);
    const [targetProduct, setTargetProduct] = useState(null);
    const [adding, setAdding] = useState(false);
    const [sessionActive, setSessionActive] = useState(false);

    useEffect(() => {
        loadCategories();
        checkSession();
    }, []);

    useEffect(() => {
        loadProducts();
    }, [activeCategory]);

    const loadCategories = async () => {
        try {
            const response = await fetch(`${BASE}/categories/sub/party-order`);
            if (response.ok) {
                const data = await response.json();
                setCategories(data || []);
            }
        } catch (err) {
            console.error('Error loading categories:', err);
        }
    };

    const loadProducts = async () => {
        setLoading(true);
        try {
            // Fetch products specifically for active category
            const response = await fetch(`${BASE}/most-ordered?category=${activeCategory}&per_page=${limit}`);
            if (!response.ok) throw new Error('Failed to fetch products');
            const data = await response.json();
            setProducts(data || []);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    const checkSession = async () => {
        try {
            const response = await fetch(`${BASE}/session-info`);
            if (response.ok) {
                const data = await response.json();
                // If date or order_mode is set, it's a preorder session
                if (data.date || data.order_mode) {
                    setSessionActive(true);
                }
            }
        } catch (err) {
            console.error('Error checking session:', err);
        }
    };

    const handleAddToCart = async (product) => {
        setAdding(product.id);
        try {
            // Fetch all grouped cart data to check for any active pre-order session in any menu
            const response = await fetch(`${BASE}/order-session/grouped-cart`);
            const groups = response.ok ? await response.json() : [];
            
            // Conflict if any group in the cart has an active pre-order session
            const hasPreOrderSession = groups.some(group => group.session && group.session.order_mode);
            
            if (hasPreOrderSession) {
                setTargetProduct(product);
                setShowConfirm(true);
                setAdding(false);
            } else {
                await executeAddToCart(product);
            }
        } catch (err) {
            console.error('Error during add to cart conflict check:', err);
            await executeAddToCart(product);
        }
    };

    const executeAddToCart = async (product) => {
        setAdding(product.id);
        try {
            const cart = await addToCart(product.id, 1);
            
            // Update UI components via standard theme module
            updateMiniCart(cart);

            // Show nice toast notification
            showToast("Product added to cart!", "success");
        } catch (err) {
            showToast(err.message || 'Error adding to cart', "error");
        } finally {
            setAdding(false);
        }
    };

    const confirmClearAndAdd = async () => {
        setShowConfirm(false);
        setAdding(targetProduct.id);
        
        // Get nonce from the specific location used by this theme's Store API
        const nonce = window.wcBlocksMiddlewareConfig?.storeApiNonce || 
                      window.zippy_params?.nonce || 
                      window.wpApiSettings?.nonce || 
                      "";
        
        try {
            // 1. Clear cart items via Store API
            await fetch('/wp-json/wc/store/v1/cart/items', { 
                method: 'DELETE',
                headers: { 
                    'X-WC-Store-API-Nonce': nonce,
                    'Nonce': nonce,
                    'X-WP-Nonce': nonce
                }
            });

            // 2. Clear our custom session using GET to bypass strict nonce checks
            const clearRes = await fetch(`${BASE}/order-session/clear`, {
                method: "GET"
            });
            
            if (clearRes.ok) {
                setSessionActive(false);
                await executeAddToCart(targetProduct);
                // Refresh to ensure all UI components sync up
                window.location.reload();
            } else {
                const errData = await clearRes.json();
                throw new Error(errData.message || 'Failed to clear current session');
            }
        } catch (err) {
            console.error('Clear session error:', err);
            showToast(err.message, "error");
            setAdding(false);
        }
    };

    if (error && products.length === 0) return <div className="party-order-error" style={{ padding: '4rem', textAlign: 'center', color: 'red' }}>Error: {error}</div>;
    
    if (products.length === 0 && loading && categories.length === 0) {
        return <div className="party-order-loading" style={{ padding: '4rem', textAlign: 'center' }}>Loading...</div>;
    }

    return (
        <div className="party-order-container">
            {categories.length > 0 && (
                <div className="party-order-filter">
                    <button 
                        className={`party-order-filter__item ${activeCategory === 'party-order' ? 'is-active' : ''}`}
                        onClick={() => setActiveCategory('party-order')}
                    >
                        Tất cả
                    </button>
                    {categories.map(cat => (
                        <button 
                            key={cat.id}
                            className={`party-order-filter__item ${activeCategory === cat.slug ? 'is-active' : ''}`}
                            onClick={() => setActiveCategory(cat.slug)}
                        >
                            {cat.name}
                        </button>
                    ))}
                </div>
            )}

            <div className={`party-order-grid columns-${columns} ${loading ? 'is-loading' : ''}`}>
                {loading && (
                    <div className="party-order-grid-overlay">
                         <div className="spinner"></div>
                    </div>
                )}
                {products.length === 0 && !loading ? (
                    <div className="party-order-empty" style={{ gridColumn: '1 / -1', padding: '4rem', textAlign: 'center', background: '#f9f9f9', borderRadius: '12px' }}>
                        <p>No products found in this category.</p>
                    </div>
                ) : (
                    products.map(product => (
                        <div key={product.id} className="party-order-card">
                            <div className="party-order-card__image">
                                <img src={product.image} alt={product.name} />
                            </div>
                            <div className="party-order-card__content">
                                <h3 className="party-order-card__title">{product.name}</h3>
                                <p className="party-order-card__desc">Sản phẩm Party Order chất lượng cao</p>
                                <div className="party-order-card__footer">
                                    <span className="party-order-card__price">${product.price.toFixed(2)}</span>
                                    <button 
                                        className="party-order-card__add"
                                        onClick={() => handleAddToCart(product)}
                                        disabled={adding === product.id}
                                    >
                                        {adding === product.id ? '...' : '+'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))
                )}
            </div>

            {showConfirm && (
                <div className="zippy-lightbox-overlay" style={{ display: 'flex', zIndex: 10001, opacity: 1, backgroundColor: 'rgba(0,0,0,0.6)', position: 'fixed', top: 0, left: 0, width: '100%', height: '100%', alignItems: 'center', justifyContent: 'center' }}>
                    <div className="zippy-lightbox-modal" style={{ maxWidth: '420px', borderRadius: '20px', overflow: 'hidden', boxShadow: '0 20px 40px rgba(0,0,0,0.2)', background: '#fff' }}>
                        <div className="zippy-lightbox-content" style={{ padding: '32px', textAlign: 'center' }}>
                            <div style={{ width: '60px', height: '60px', background: '#fff5ed', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 20px' }}>
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#df6f22" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            </div>
                            <h3 style={{ margin: '0 0 12px', color: '#1a1a1a', fontSize: '22px', fontWeight: '700' }}>Cart Conflict</h3>
                            <p style={{ margin: '0 0 28px', color: '#666', fontSize: '15px', lineHeight: '1.6' }}>
                                Your cart contains <strong>Pre-order</strong> items. Starting a <strong>Party Order</strong> will clear all current items. Do you want to continue?
                            </p>
                            <div style={{ display: 'flex', gap: '12px' }}>
                                <button 
                                    className="zippy-button"
                                    onClick={() => setShowConfirm(false)}
                                    style={{ flex: 1, height: '48px', borderRadius: '12px', background: '#f5f5f5', color: '#444', border: 'none', fontWeight: '600', cursor: 'pointer' }}
                                >
                                    Cancel
                                </button>
                                <button 
                                    className="zippy-button"
                                    onClick={confirmClearAndAdd}
                                    style={{ flex: 1, height: '48px', borderRadius: '12px', background: '#df6f22', color: '#fff', border: 'none', fontWeight: '600', cursor: 'pointer', boxShadow: '0 4px 12px rgba(223, 111, 34, 0.3)' }}
                                >
                                    Clear & Continue
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
