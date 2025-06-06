import { useEffect, useState } from 'react';
import './TablePrio.css';
import { useParams } from 'react-router-dom';

interface SimulationResult {
    arret: number;
    redemarrage: number;
    journee: number[];
    profit: number;
}

interface ResponseData {
    tableau: SimulationResult[];
    valeurNormale: number;
    message: string;
}

export default function TableAD() {
    const { nom } = useParams<{ nom: string }>();
    const [data, setData] = useState<ResponseData | null>(null);
    const [loading, setLoading] = useState<boolean>(true);

    useEffect(() => {
        if (!nom) return;

        fetch(`http://localhost/manipRTE.php?action=TableAD&nom=${encodeURIComponent(nom)}`)
            .then((res) => res.json())
            .then((json: ResponseData) => {
                setData(json);
                setLoading(false);
            })
            .catch((err) => {
                console.error("Erreur de chargement :", err);
                setLoading(false);
            });
    }, [nom]);

    if (loading) return <p>Chargement des données...</p>;
    if (!data) return <p>Erreur : données non disponibles</p>;

    const meilleure = data.tableau.reduce((best, curr) => {
        return curr.profit > best.profit ? curr : best;
    }, data.tableau[0]);

    const pourcentageGain = ((meilleure.profit - data.valeurNormale) / data.valeurNormale) * 100;

    return (
        <div>
            <div className="table-wrapper">
                <table className="tableAD">
                    <thead>
                    <tr>
                        <th>Arrêt</th>
                        <th>Démarrage</th>
                        {Array.from({ length: 24 }, (_, i) => (
                            <th key={i}>{i}h</th>
                        ))}
                        <th>Tarif estimé</th>
                    </tr>
                    </thead>
                    <tbody>
                    {data.tableau.map((item, idx) => (
                        <tr
                            key={idx}
                            className={
                                item.arret === meilleure.arret &&
                                item.redemarrage === meilleure.redemarrage &&
                                item.profit === meilleure.profit
                                    ? 'ligne-meilleure'
                                    : ''
                            }
                        >
                            <td>{item.arret}h</td>
                            <td>{item.redemarrage}h</td>
                            {item.journee.map((val, i) => (
                                <td key={i}>{val.toFixed(2)}</td>
                            ))}
                            <td>{item.profit.toFixed(2)} €</td>
                        </tr>
                    ))}
                    </tbody>
                </table>
            </div>

            <div className="card">
                <h2>Résumé</h2>
                <p><strong>Tarif sans arrêt/redémarrage :</strong> {data.valeurNormale.toFixed(2)} €</p>
                <p>
                    <strong>Meilleur tarif avec arrêt/redémarrage :</strong> {meilleure.profit.toFixed(2)} €
                    <span style={{ color: 'green', marginLeft: '8px' }}>
                        (+{pourcentageGain.toFixed(2)}%)
                    </span>
                </p>
                <p><strong>Message :</strong> {data.message}</p>
            </div>
        </div>
    );
}
