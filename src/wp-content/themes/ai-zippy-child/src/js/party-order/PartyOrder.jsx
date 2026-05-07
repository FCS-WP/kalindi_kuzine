import { useState, useEffect } from 'react';
import { addToCart } from '@/js/modules/cart-api';
import './style.scss';

// Reuse API base from parent theme
const BASE = "/wp-json/ai-zippy/v1";

export default function PartyOrder({ limit, columns }) {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showConfirm, setShowConfirm] = useState(false);
    const [targetProduct, setTargetProduct] = useState(null);
    const [adding, setAdding] = useState(false);
    const [sessionActive, setSessionActive] = useState(false);

    useEffect(() => {
        loadProducts();
        checkSession();
    }, []);

    const loadProducts = async () => {
        try {
            // Fetch products specifically for party-order category
            const response = await fetch(`${BASE}/most-ordered?category=party-order&per_page=${limit}`);
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

    const handleAddToCart = (product) => {
        if (sessionActive) {
            setTargetProduct(product);
            setShowConfirm(true);
        } else {
            executeAddToCart(product);
        }
    };

    const executeAddToCart = async (product) => {
        setAdding(product.id);
        try {
            await addToCart(product.id, 1);
            // Trigger cart refresh events for other components
            document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart'));
            const $ = window.jQuery;
            if ($) {
                $(document.body).trigger('added_to_cart', [null, null, null]);
            }
            alert('Sản phẩm đã được thêm vào giỏ hàng!');
        } catch (err) {
            alert('Lỗi khi thêm vào giỏ hàng: ' + err.message);
        } finally {
            setAdding(false);
        }
    };

    const confirmClearAndAdd = async () => {
        setShowConfirm(false);
        setAdding(targetProduct.id);
        try {
            // Clear session first
            const clearRes = await fetch(`${BASE}/order-session/clear`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-WP-Nonce": window.wpApiSettings?.nonce || ""
                }
            });
            
            if (clearRes.ok) {
                setSessionActive(false);
                await executeAddToCart(targetProduct);
            } else {
                throw new Error('Không thể xóa session hiện tại');
            }
        } catch (err) {
            alert(err.message);
            setAdding(false);
        }
    };

    if (loading) return <div className="party-order-loading" style={{ padding: '4rem', textAlign: 'center' }}>Đang tải sản phẩm...</div>;
    if (error) return <div className="party-order-error" style={{ padding: '4rem', textAlign: 'center', color: 'red' }}>Lỗi: {error}</div>;
    if (products.length === 0) {
        return (
            <div className="party-order-empty" style={{ padding: '4rem', textAlign: 'center', background: '#f9f9f9', borderRadius: '12px' }}>
                <p>Không tìm thấy sản phẩm nào trong danh mục <strong>party-order</strong>.</p>
                <p style={{ fontSize: '0.9rem', color: '#666' }}>Vui lòng gán category "party-order" cho các sản phẩm bạn muốn hiển thị ở đây.</p>
            </div>
        );
    }

    return (
        <div className={`party-order-grid columns-${columns}`}>
            {products.map(product => (
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
            ))}

            {showConfirm && (
                <div className="party-order-modal">
                    <div className="party-order-modal__content">
                        <h3>Cảnh báo!</h3>
                        <p>Bạn đang có một phiên đặt hàng Pre-order. Việc thêm sản phẩm Party Order sẽ xóa tất cả thông tin Pre-order hiện tại. Bạn có muốn tiếp tục?</p>
                        <div className="party-order-modal__actions">
                            <button className="btn-cancel" onClick={() => setShowConfirm(false)}>Hủy</button>
                            <button className="btn-confirm" onClick={confirmClearAndAdd}>Đồng ý & Tiếp tục</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
