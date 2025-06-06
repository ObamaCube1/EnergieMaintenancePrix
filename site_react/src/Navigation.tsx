import { useParams, useNavigate } from "react-router-dom";
import './Navigation.css';

export default function Navigation() {
    const {nom} = useParams<{ nom: string }>();
    const navigate = useNavigate();

    return (
        <nav>
            <div className="services-bar">
                <div className="service-bar-item" onClick={() => navigate(`/${nom}/config`)}>
                    Configuration
                </div>
                <div className="service-bar-item" onClick={() => navigate(`/${nom}/calcul`)}>
                    Calcul
                </div>
            </div>
        </nav>
    );
}